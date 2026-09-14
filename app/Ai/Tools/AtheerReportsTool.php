<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\Atheer\AtheerApiClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Lets the second brain pull live figures from the Atheer ERP so it can answer
 * questions like "how many deliveries right now?" or "how much money is
 * unaccounted for?". The model picks a report; this returns the raw JSON for
 * the model to phrase.
 */
final class AtheerReportsTool implements Tool
{
    private const REPORTS = ['summary', 'funnel', 'deliveries', 'reconciliation', 'finance'];

    public function __construct(private readonly AtheerApiClient $atheer) {}

    public function description(): string
    {
        return <<<'TEXT'
        Fetch live business data from the Atheer ERP. Reports:
        - summary: everything at a glance (funnel + deliveries + reconciliation + this month's finance)
        - funnel: number of Instagram leads at each pipeline stage
        - deliveries: parcels in transit, delivered today, and flagged for follow-up
        - reconciliation: deliveries that shipped but were never paid or refunded (money to chase), with total amount
        - finance: revenue, profit, refunds and net for a period (optional from/to dates, YYYY-MM-DD; defaults to this month)
        Use this whenever the owner asks about Atheer sales, leads, deliveries, or money.
        TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'report' => $schema->string()
                ->description('Which report to fetch.')
                ->enum(self::REPORTS)
                ->required(),
            'from' => $schema->string()
                ->description('Finance only: start date YYYY-MM-DD.'),
            'to' => $schema->string()
                ->description('Finance only: end date YYYY-MM-DD.'),
        ];
    }

    public function handle(Request $request): string
    {
        $report = (string) ($request['report'] ?? 'summary');

        if (! in_array($report, self::REPORTS, true)) {
            $report = 'summary';
        }

        try {
            $data = match ($report) {
                'funnel' => $this->atheer->funnel(),
                'deliveries' => $this->atheer->deliveries(),
                'reconciliation' => $this->atheer->reconciliation(),
                'finance' => $this->atheer->finance($request['from'] ?? null, $request['to'] ?? null),
                default => $this->atheer->summary(),
            };
        } catch (Throwable $e) {
            report($e);

            return 'Atheer data is temporarily unavailable.';
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
