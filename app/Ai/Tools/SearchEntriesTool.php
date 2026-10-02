<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Support\HouseLookup;
use App\Ai\Support\OwnerResolver;
use App\Ai\Support\ToolResponse;
use App\Models\Entry;
use App\Support\Dashboard\Sections;
use App\Support\Money\EntryTotals;
use App\Support\Reports\Range;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The read side of the journal. Without it the brain can only write and would
 * have to tell the owner "I cannot look that up" — which is the one thing a
 * second brain must never say. Answers "how much sadaqa did I give last
 * month?", "what did I note about the supplier?", "show my health entries",
 * "how much has the Chilanzar house cost so far?".
 *
 * Returns both the matching entries (with their ids, so they can be edited)
 * and pre-computed totals, so the model never has to add numbers itself.
 *
 * @phpstan-import-type Totals from EntryTotals
 */
final class SearchEntriesTool implements Tool
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    /** Entries are short; this only guards against a pasted wall of text. */
    private const BODY_LIMIT = 400;

    public function __construct(
        private readonly OwnerResolver $owner,
        private readonly HouseLookup $houses,
    ) {}

    public function description(): string
    {
        $sections = implode(', ', Sections::entryKeys());
        $periods = implode(', ', Range::KEYWORDS);
        $houses = $this->houses->describe($this->owner->id());

        return <<<TEXT
        Look up what the owner has already logged. Use this for ANY question
        about the past — "how much did I give to charity last month", "what did
        I write about the supplier", "show my health notes", "how much did I
        spend on food", "how much has this house cost" — before saying you do
        not know something.

        Sections: {$sections}. Omit to search all of them.
        Periods: {$periods}. Defaults to this_month; pass "all" when the owner
        does not name a time frame and you want their whole history. You may
        instead pass explicit from/to dates (YYYY-MM-DD), which win over period.
        House: narrows to one home-business house, by its name as listed. The
        owner's houses: {$houses}. A house's total cost so far is period "all".

        Returns the matching entries newest first (each with its id, date,
        amount, house and text), plus ready-made totals per section, per house
        and overall. Amounts are signed: income positive, expense negative;
        "expense" in the totals is already the absolute value of what went out.
        Use the returned ids when the owner asks to correct or move an entry.
        TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sections' => $schema->array()
                ->items($schema->string()->enum(Sections::entryKeys()))
                ->description('Sections to search. Omit for all of them.'),
            'house' => $schema->string()->description('Only this home-business house, by its name as listed.'),
            'period' => $schema->string()
                ->description('Relative time window.')
                ->enum(Range::KEYWORDS),
            'from' => $schema->string()->description('Start date YYYY-MM-DD (overrides period).'),
            'to' => $schema->string()->description('End date YYYY-MM-DD (overrides period).'),
            'query' => $schema->string()->description('Optional text to match inside the entry body.'),
            'limit' => $schema->integer()->description('How many entries to return (default 20, max 100).'),
        ];
    }

    public function handle(Request $request): string
    {
        $ownerId = $this->owner->id();

        if ($ownerId === null) {
            return 'Could not search: no owner account is configured.';
        }

        $sections = $this->normalizeSections($request['sections'] ?? null);

        if (is_string($sections)) {
            return $sections;
        }

        $houseName = $this->asString($request['house'] ?? null);
        $house = $houseName === null ? null : $this->houses->find($ownerId, $houseName);

        if ($houseName !== null && $house === null) {
            return 'Could not search: no single house matches "'.$houseName.'". The owner\'s houses: '
                .$this->houses->describe($ownerId).'.';
        }

        $houseId = $house?->id;

        $range = Range::resolve(
            $this->asString($request['period'] ?? null),
            $this->asString($request['from'] ?? null),
            $this->asString($request['to'] ?? null),
        );

        $query = trim((string) ($request['query'] ?? ''));

        $base = Entry::query()
            ->forUser($ownerId)
            ->occurredBetween($range->from, $range->to)
            ->when($sections !== [], fn (Builder $builder) => $builder->whereIn('section', $sections))
            ->when($houseId !== null, fn (Builder $builder) => $builder->forHouse($houseId))
            ->when($query !== '', fn (Builder $builder) => $builder->where('body', 'like', '%'.$query.'%'));

        $bySection = $this->statistics($base);
        $byHouse = $this->houseStatistics($base, $ownerId);
        $entries = (clone $base)
            ->with('house:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($this->normalizeLimit($request['limit'] ?? null))
            ->get();

        return ToolResponse::json([
            'range' => $range->toArray(),
            'sections' => $sections === [] ? Sections::entryKeys() : $sections,
            'house' => $house?->name,
            'query' => $query === '' ? null : $query,
            'matched' => array_sum(array_column($bySection, 'entries')),
            'returned' => $entries->count(),
            'totals' => [
                'sum' => round((float) array_sum(array_column($bySection, 'sum')), 2),
                'income' => round((float) array_sum(array_column($bySection, 'income')), 2),
                'expense' => round((float) array_sum(array_column($bySection, 'expense')), 2),
            ],
            'by_section' => $bySection,
            ...($byHouse === [] ? [] : ['by_house' => $byHouse]),
            'entries' => $entries->map($this->present(...))->all(),
        ]);
    }

    /**
     * Per-section counts and money totals over every match — not just the page
     * of entries returned — so "how much in total" is always answerable.
     *
     * @param  Builder<Entry>  $base
     * @return list<array{section:string,label:string,entries:int,sum:float,income:float,expense:float}>
     */
    private function statistics(Builder $base): array
    {
        $stats = [];

        foreach (EntryTotals::groupedBy($base, 'section') as $group) {
            $key = (string) $group['key'];

            $stats[] = [
                'section' => $key,
                'label' => Sections::find($key)['label'] ?? $key,
                ...$this->money($group['totals']),
            ];
        }

        return $stats;
    }

    /**
     * The same totals per house, over the matched entries of sections split by
     * house — "how much has each house cost" in one call. A null house is the
     * entries filed under none. Empty when no such entries matched.
     *
     * @param  Builder<Entry>  $base
     * @return list<array{house:string|null,entries:int,sum:float,income:float,expense:float}>
     */
    private function houseStatistics(Builder $base, int $ownerId): array
    {
        $houseSections = Sections::houseKeys();

        if ($houseSections === []) {
            return [];
        }

        $names = $this->houses->all($ownerId)->pluck('name', 'id');
        $stats = [];

        foreach (EntryTotals::groupedBy((clone $base)->whereIn('section', $houseSections), 'house_id') as $group) {
            $stats[] = [
                'house' => $group['key'] === null ? null : $names->get((int) $group['key']),
                ...$this->money($group['totals']),
            ];
        }

        return $stats;
    }

    /**
     * The tool has always called the net "sum"; keep that name for the model.
     *
     * @param  Totals  $totals
     * @return array{entries:int,sum:float,income:float,expense:float}
     */
    private function money(array $totals): array
    {
        return [
            'entries' => $totals['entries'],
            'sum' => $totals['net'],
            'income' => $totals['income'],
            'expense' => $totals['expense'],
        ];
    }

    /**
     * @return list<string>|string  The validated keys, or an error message.
     */
    private function normalizeSections(mixed $sections): array|string
    {
        if ($sections === null || $sections === '' || $sections === []) {
            return [];
        }

        $keys = is_array($sections) ? $sections : [$sections];
        $clean = [];

        foreach ($keys as $key) {
            $key = is_string($key) ? trim($key) : '';

            if (Sections::entrySection($key) === null) {
                return 'Could not search: "'.$key.'" is not a valid section.';
            }

            $clean[] = $key;
        }

        return array_values(array_unique($clean));
    }

    private function normalizeLimit(mixed $limit): int
    {
        if (! is_numeric($limit)) {
            return self::DEFAULT_LIMIT;
        }

        return max(1, min(self::MAX_LIMIT, (int) $limit));
    }

    private function asString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array{id:int,section:string,house:string|null,date:string,amount:float|null,body:string,tags:list<string>}
     */
    private function present(Entry $entry): array
    {
        /** @var array<int, string> $tags */
        $tags = $entry->tags ?? [];

        return [
            'id' => $entry->id,
            'section' => $entry->section,
            'house' => $entry->house?->name,
            'date' => $entry->occurred_at->toDateString(),
            'amount' => $entry->amount !== null ? (float) $entry->amount : null,
            'body' => Str::limit($entry->body, self::BODY_LIMIT),
            'tags' => array_values($tags),
        ];
    }
}
