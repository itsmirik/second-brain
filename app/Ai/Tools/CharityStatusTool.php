<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Support\OwnerResolver;
use App\Ai\Support\ToolResponse;
use App\Support\Charity\CharityService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The sadaqa ledger: what is owed this month, what has already been given, and
 * what is left. Same maths as the dashboard's charity page (profit before
 * giving, times the owner's percentage).
 */
final class CharityStatusTool implements Tool
{
    private const DEFAULT_MONTHS = 3;

    private const MAX_MONTHS = 12;

    public function __construct(
        private readonly OwnerResolver $owner,
        private readonly CharityService $charity,
    ) {}

    public function description(): string
    {
        return <<<'TEXT'
        The owner's charity (sadaqa) obligation, month by month, newest first.
        Use it for "how much sadaqa do I owe", "did I give enough this month",
        "what is left to give".

        Each month returns: profit (the base, before any giving), the
        percentage, the resulting obligation, how much was actually given, and
        what remains. A negative "remaining" means they gave more than owed.
        To list the individual donations instead, search the charity section.
        TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'months' => $schema->integer()
                ->description('How many recent months to return (default 3, max 12).'),
        ];
    }

    public function handle(Request $request): string
    {
        $ownerId = $this->owner->id();

        if ($ownerId === null) {
            return 'Could not read the charity status: no owner account is configured.';
        }

        $months = $request['months'] ?? null;
        $count = is_numeric($months)
            ? max(1, min(self::MAX_MONTHS, (int) $months))
            : self::DEFAULT_MONTHS;

        return ToolResponse::json([
            'percentage' => $this->charity->percentage($ownerId),
            'months' => $this->charity->months($ownerId, $count),
        ]);
    }
}
