<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Support\OwnerResolver;
use App\Ai\Support\ToolResponse;
use App\Support\Money\MoneyReporter;
use App\Support\Reports\Range;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * "How did I do this month?" answered across everything at once: the Atheer
 * ERP plus the money-bearing journal sections, netted by the same
 * MoneyReporter the dashboard's Reports page uses — so the bot and the screen
 * can never disagree.
 */
final class MoneyReportTool implements Tool
{
    public function __construct(
        private readonly OwnerResolver $owner,
        private readonly MoneyReporter $money,
    ) {}

    public function description(): string
    {
        $periods = implode(', ', Range::KEYWORDS);

        return <<<TEXT
        Net money picture for a period across ALL sources at once: the Atheer
        ERP plus the owner's money sections (household budget, home business).
        Use it for "how much did I earn / spend / what is my profit" questions
        that are not about Atheer alone — for Atheer-only figures use the Atheer
        reports tool, for a list of individual records use the entry search.

        Periods: {$periods} (default this_month), or explicit from/to dates
        (YYYY-MM-DD), which win over period.

        Returns per-source income / expense / net, the grand net (the profit the
        charity obligation is based on), and how much charity was given in that
        window. A source with available=false was unreachable — say so instead
        of reporting its zeros as fact.
        TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->description('Relative time window.')
                ->enum(Range::KEYWORDS),
            'from' => $schema->string()->description('Start date YYYY-MM-DD (overrides period).'),
            'to' => $schema->string()->description('End date YYYY-MM-DD (overrides period).'),
        ];
    }

    public function handle(Request $request): string
    {
        $ownerId = $this->owner->id();

        if ($ownerId === null) {
            return 'Could not build the report: no owner account is configured.';
        }

        $range = Range::resolve(
            is_string($request['period'] ?? null) ? $request['period'] : null,
            is_string($request['from'] ?? null) ? $request['from'] : null,
            is_string($request['to'] ?? null) ? $request['to'] : null,
        );

        $report = $this->money->report($ownerId, $range->from, $range->to);

        return ToolResponse::json([
            'range' => $range->toArray(),
            ...$report,
        ]);
    }
}
