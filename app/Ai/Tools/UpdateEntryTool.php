<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Support\OwnerResolver;
use App\Models\Entry;
use App\Support\Dashboard\Sections;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Date;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Corrects an entry that is already in the journal — a wrong amount, a typo, a
 * date, or a note that was filed under the wrong section. The id comes from
 * the entry search tool, and only the owner's own entries can be touched.
 */
final class UpdateEntryTool implements Tool
{
    public function __construct(private readonly OwnerResolver $owner) {}

    public function description(): string
    {
        $sections = implode(', ', Sections::entryKeys());

        return <<<TEXT
        Fix an entry that was already saved: change its text, amount, date, tags,
        or move it to another section ({$sections}).

        Find the entry with the search tool first — this needs its id. Only pass
        the fields that change; everything else stays as it is. Amounts are
        signed: income positive, expense negative (spent 80000 -> -80000).
        Moving an entry into a section that carries no money clears its amount.

        Never guess an id. If the owner's description matches several entries,
        show them what you found and ask which one.
        TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Id of the entry to change, from the search tool.')
                ->required(),
            'section' => $schema->string()
                ->description('Move the entry to this section.')
                ->enum(Sections::entryKeys()),
            'body' => $schema->string()->description('Replacement entry text.'),
            'amount' => $schema->number()->description('Replacement signed amount in so\'m.'),
            'occurred_at' => $schema->string()->description('Replacement date, YYYY-MM-DD.'),
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Replacement labels. Replaces the existing ones.'),
        ];
    }

    public function handle(Request $request): string
    {
        $ownerId = $this->owner->id();

        if ($ownerId === null) {
            return 'Could not update that: no owner account is configured.';
        }

        $id = $request['id'] ?? null;

        if (! is_numeric($id)) {
            return 'Could not update that: a numeric entry id is required. Search for the entry first.';
        }

        $entry = Entry::query()->forUser($ownerId)->find((int) $id);

        if ($entry === null) {
            return 'No entry #'.(int) $id.' in your journal — search again and use the id it returns.';
        }

        $section = $request['section'] ?? null;
        $target = $section === null
            ? Sections::entrySection($entry->section)
            : Sections::entrySection(is_string($section) ? $section : '');

        if ($target === null) {
            return 'Could not update that: "'.(is_string($section) ? $section : $entry->section).'" is not a valid section.';
        }

        $changes = $this->changes($request, $entry, $target);

        if ($changes === []) {
            return 'Nothing to change — tell me what to correct (text, amount, date, section or tags).';
        }

        try {
            $entry->update($changes);
        } catch (Throwable $e) {
            report($e);

            return 'Could not save that change right now. Please try again shortly.';
        }

        $entry->refresh();

        $money = $entry->amount !== null ? ', '.$entry->amount.' so\'m' : '';

        return "Updated entry #{$entry->id}: {$target['label']}, {$entry->occurred_at->toDateString()}{$money} — {$entry->body}";
    }

    /**
     * Only the fields the model actually sent, normalized.
     *
     * @param  array{key:string,label:string,description:string,icon:string,driver:string,money:bool,status:string}  $target
     * @return array<string, mixed>
     */
    private function changes(Request $request, Entry $entry, array $target): array
    {
        $changes = [];

        if ($target['key'] !== $entry->section) {
            $changes['section'] = $target['key'];
        }

        $body = $request['body'] ?? null;

        if (is_string($body) && trim($body) !== '') {
            $changes['body'] = trim($body);
        }

        $date = $this->normalizeDate($request['occurred_at'] ?? null);

        if ($date !== null) {
            $changes['occurred_at'] = $date;
        }

        $tags = $this->normalizeTags($request['tags'] ?? null);

        if ($tags !== null) {
            $changes['tags'] = $tags;
        }

        // A section that carries no money can never keep an amount; otherwise
        // the amount only changes when the model sent a new one.
        if (! $target['money']) {
            if ($entry->amount !== null) {
                $changes['amount'] = null;
            }
        } elseif (is_numeric($request['amount'] ?? null)) {
            $changes['amount'] = (string) $request['amount'];
        }

        return $changes;
    }

    private function normalizeDate(mixed $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return Date::parse($date)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>|null
     */
    private function normalizeTags(mixed $tags): ?array
    {
        if (! is_array($tags)) {
            return null;
        }

        $clean = [];

        foreach ($tags as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $clean[] = trim($tag);
            }
        }

        return $clean;
    }
}
