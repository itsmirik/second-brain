<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sections;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\House;
use App\Support\Dashboard\Sections;
use App\Support\Money\EntryTotals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Generic, entries-backed life sections (Personal, Health, Budget, ...). Each
 * is a dated log of free-text entries with an optional money amount and tags.
 * Atheer is NOT handled here — it has its own external-data controller.
 *
 * A section split by house (config "houses") also switches between the
 * owner's houses: ?house=<id> shows one house, ?house=none the entries filed
 * under no house, and no parameter shows them all.
 *
 * @phpstan-import-type Totals from EntryTotals
 */
class SectionController extends Controller
{
    private const RECENT_LIMIT = 200;

    /** The ?house= value that selects the entries filed under no house. */
    private const NO_HOUSE = 'none';

    public function show(Request $request, string $section): Response
    {
        $meta = Sections::entrySection($section) ?? abort(404);
        $userId = (int) $request->user()->getAuthIdentifier();

        $all = Entry::query()->forUser($userId)->forSection($section);
        $houses = Sections::hasHouses($section) ? House::ownedBy($userId) : null;
        $house = $houses === null ? null : $this->selectedHouse($request->query('house'), $houses);
        $shown = $this->inHouse(clone $all, $house);

        $entries = (clone $shown)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        $breakdown = $houses === null ? null : $this->houseBreakdown($all, $houses);

        return Inertia::render('sections/Entries', [
            'section' => $meta,
            'entries' => $entries->map($this->present(...))->all(),
            'totals' => $meta['money'] ? EntryTotals::of($shown) : null,
            'houses' => $breakdown['houses'] ?? null,
            'unassigned' => $breakdown['unassigned'] ?? null,
            'house' => $house,
        ]);
    }

    public function storeEntry(Request $request, string $section): RedirectResponse
    {
        $meta = Sections::entrySection($section) ?? abort(404);
        $userId = (int) $request->user()->getAuthIdentifier();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'direction' => ['nullable', 'in:income,expense'],
            'occurred_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'house_id' => ['nullable', 'integer', $this->ownHouse($userId)],
        ]);

        Entry::query()->create([
            'user_id' => $userId,
            'section' => $meta['key'],
            'house_id' => Sections::hasHouses($meta['key']) ? $this->houseId($validated['house_id'] ?? null) : null,
            'body' => $validated['body'],
            'amount' => $meta['money']
                ? $this->signedAmount($validated['amount'] ?? null, $validated['direction'] ?? null)
                : null,
            'occurred_at' => $validated['occurred_at'] ?? Date::today()->toDateString(),
            'tags' => $validated['tags'] ?? null,
        ]);

        return back();
    }

    /**
     * Edit an entry, including moving it to a different section or house. This
     * is the owner's recourse when the bot files something in the wrong place.
     */
    public function updateEntry(Request $request, Entry $entry): RedirectResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        abort_unless((int) $entry->user_id === $userId, 403);

        $validated = $request->validate([
            'section' => ['required', 'string', Rule::in(Sections::entryKeys())],
            'body' => ['required', 'string', 'max:5000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'direction' => ['nullable', 'in:income,expense'],
            'occurred_at' => ['nullable', 'date'],
            'house_id' => ['nullable', 'integer', $this->ownHouse($userId)],
        ]);

        $target = Sections::entrySection($validated['section']) ?? abort(404);

        // A request that does not mention the house keeps the current one.
        $houseId = array_key_exists('house_id', $validated)
            ? $this->houseId($validated['house_id'])
            : $entry->house_id;

        $entry->update([
            'section' => $target['key'],
            'house_id' => Sections::hasHouses($target['key']) ? $houseId : null,
            'body' => $validated['body'],
            'amount' => $target['money']
                ? $this->signedAmount($validated['amount'] ?? null, $validated['direction'] ?? null)
                : null,
            'occurred_at' => $validated['occurred_at'] ?? $entry->occurred_at->toDateString(),
        ]);

        return back();
    }

    public function destroyEntry(Request $request, Entry $entry): RedirectResponse
    {
        abort_unless(
            (int) $entry->user_id === (int) $request->user()->getAuthIdentifier(),
            403,
        );

        $entry->delete();

        return back();
    }

    /**
     * The house the page shows: its id, NO_HOUSE, or null for every house. An
     * unknown id (a deleted house, or someone else's) falls back to all.
     *
     * @param  Collection<int, House>  $houses
     */
    private function selectedHouse(mixed $value, Collection $houses): int|string|null
    {
        if ($value === self::NO_HOUSE) {
            return self::NO_HOUSE;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $houses->contains('id', $id) ? $id : null;
    }

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    private function inHouse(Builder $query, int|string|null $house): Builder
    {
        return match (true) {
            $house === null => $query,
            $house === self::NO_HOUSE => $query->forHouse(null),
            default => $query->forHouse((int) $house),
        };
    }

    /**
     * Every house with its all-time totals in the section (a house without
     * entries shows zeros), plus the entries filed under no house — null
     * when there are none, so the page only offers that view when it matters.
     *
     * @param  Builder<Entry>  $all
     * @param  Collection<int, House>  $houses
     * @return array{houses: list<array{id:int,name:string,totals:Totals}>, unassigned: Totals|null}
     */
    private function houseBreakdown(Builder $all, Collection $houses): array
    {
        $byHouse = [];
        $unassigned = null;

        foreach (EntryTotals::groupedBy($all, 'house_id') as $group) {
            if ($group['key'] === null) {
                $unassigned = $group['totals'];
            } else {
                $byHouse[(int) $group['key']] = $group['totals'];
            }
        }

        $summaries = [];

        foreach ($houses as $house) {
            $summaries[] = [
                'id' => $house->id,
                'name' => $house->name,
                'totals' => $byHouse[$house->id] ?? EntryTotals::zero(),
            ];
        }

        return ['houses' => $summaries, 'unassigned' => $unassigned];
    }

    /**
     * Validation rule: a house that belongs to this owner.
     */
    private function ownHouse(int $userId): Exists
    {
        return Rule::exists('houses', 'id')->where('user_id', $userId);
    }

    private function houseId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Turn a positive amount + direction into the stored signed value
     * (income positive, expense negative). Charity giving has no direction and
     * is treated as a positive amount.
     */
    private function signedAmount(mixed $amount, ?string $direction): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $value = abs((float) $amount);

        if ($direction === 'expense') {
            $value = -$value;
        }

        return (string) $value;
    }

    /**
     * @return array{id:int,section:string,house_id:int|null,body:string,amount:float|null,occurred_at:string,tags:list<string>}
     */
    private function present(Entry $entry): array
    {
        /** @var array<int, string> $tags */
        $tags = $entry->tags ?? [];

        return [
            'id' => $entry->id,
            'section' => $entry->section,
            'house_id' => $entry->house_id,
            'body' => $entry->body,
            'amount' => $entry->amount !== null ? (float) $entry->amount : null,
            'occurred_at' => $entry->occurred_at->toDateString(),
            'tags' => array_values($tags),
        ];
    }
}
