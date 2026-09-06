<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sections;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Support\Dashboard\Sections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
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
            'occurred_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
        ]);

        Entry::query()->create([
            'user_id' => $request->user()->getAuthIdentifier(),
            'section' => $meta['key'],
            'body' => $validated['body'],
            'amount' => $meta['money'] ? ($validated['amount'] ?? null) : null,
            'occurred_at' => $validated['occurred_at'] ?? Date::today()->toDateString(),
            'tags' => $validated['tags'] ?? null,
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
     * @return array{id:int,body:string,amount:float|null,occurred_at:string,tags:list<string>}
     */
    private function present(Entry $entry): array
    {
        /** @var array<int, string> $tags */
        $tags = $entry->tags ?? [];

        return [
            'id' => $entry->id,
            'body' => $entry->body,
            'amount' => $entry->amount !== null ? (float) $entry->amount : null,
            'occurred_at' => $entry->occurred_at->toDateString(),
            'tags' => array_values($tags),
        ];
    }
}
