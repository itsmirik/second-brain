<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sections;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Support\Dashboard\Sections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Generic, entries-backed life sections (Personal, Health, Budget, ...). Each
 * is a dated log of free-text entries with an optional money amount and tags.
 * Atheer is NOT handled here — it has its own external-data controller.
 */
class SectionController extends Controller
{
    private const RECENT_LIMIT = 200;

    public function show(Request $request, string $section): Response
    {
        $meta = Sections::entrySection($section) ?? abort(404);

        $entries = Entry::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->forSection($section)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();

        return Inertia::render('sections/Entries', [
            'section' => $meta,
            'entries' => $entries->map($this->present(...))->all(),
            'total' => $meta['money'] ? (float) $entries->sum('amount') : null,
        ]);
    }

    public function storeEntry(Request $request, string $section): RedirectResponse
    {
        $meta = Sections::entrySection($section) ?? abort(404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'direction' => ['nullable', 'in:income,expense'],
            'occurred_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
        ]);

        Entry::query()->create([
            'user_id' => $request->user()->getAuthIdentifier(),
            'section' => $meta['key'],
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
     * Edit an entry, including moving it to a different section. This is the
     * owner's recourse when the bot files something in the wrong place.
     */
    public function updateEntry(Request $request, Entry $entry): RedirectResponse
    {
        abort_unless(
            (int) $entry->user_id === (int) $request->user()->getAuthIdentifier(),
            403,
        );

        $validated = $request->validate([
            'section' => ['required', 'string', Rule::in(Sections::entryKeys())],
            'body' => ['required', 'string', 'max:5000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'direction' => ['nullable', 'in:income,expense'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $target = Sections::entrySection($validated['section']) ?? abort(404);

        $entry->update([
            'section' => $target['key'],
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
     * @return array{id:int,section:string,body:string,amount:float|null,occurred_at:string,tags:list<string>}
     */
    private function present(Entry $entry): array
    {
        /** @var array<int, string> $tags */
        $tags = $entry->tags ?? [];

        return [
            'id' => $entry->id,
            'section' => $entry->section,
            'body' => $entry->body,
            'amount' => $entry->amount !== null ? (float) $entry->amount : null,
            'occurred_at' => $entry->occurred_at->toDateString(),
            'tags' => array_values($tags),
        ];
    }
}
