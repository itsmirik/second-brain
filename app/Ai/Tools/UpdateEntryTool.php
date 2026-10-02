<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Support\HouseLookup;
use App\Ai\Support\OwnerResolver;
use App\Models\Entry;
use App\Models\House;
use App\Support\Dashboard\Sections;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Date;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Corrects an entry that is already in the journal — a wrong amount, a typo, a
 * date, a note that was filed under the wrong section, or a home-business
 * entry that belongs to another house. The id comes from the entry search
 * tool, and only the owner's own entries can be touched.
 *
 * @phpstan-import-type SectionConfig from Sections
 */
final class UpdateEntryTool implements Tool
{
    public function __construct(
        private readonly OwnerResolver $owner,
        private readonly HouseLookup $houses,
    ) {}

    public function description(): string
    {
        $sections = implode(', ', Sections::entryKeys());
        $houses = $this->houses->describe($this->owner->id());

        return <<<TEXT
        Fix an entry that was already saved: change its text, amount, date, tags,
        move it to another section ({$sections}), or file a home-business entry
        under another house (the owner's houses: {$houses}).

        Find the entry with the search tool first — this needs its id. Only pass
        the fields that change; everything else stays as it is. Amounts are
        signed: income positive, expense negative (spent 80000 -> -80000).
        Moving an entry into a section that carries no money clears its amount,
        and moving it out of home-business clears its house.

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
            'house' => $schema->string()
                ->description('home-business only: file the entry under this house, by its name as listed.'),
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

        $houseName = $request['house'] ?? null;
        $house = null;

        if (is_string($houseName) && trim($houseName) !== '') {
            if (! Sections::hasHouses($target['key'])) {
                return 'Could not update that: houses only apply to '.implode(', ', Sections::houseKeys())
                    .' entries. Move the entry there as well if that is what the owner meant.';
            }

            $house = $this->houses->find($ownerId, $houseName);

            if ($house === null) {
                return 'Could not update that: no single house matches "'.trim($houseName).'". The owner\'s houses: '
                    .$this->houses->describe($ownerId).'.';
            }
        }

        // Moved into a section split by house with none named: the only house,
        // as when logging; with several, the reply asks which one.
        $movesIntoHouses = Sections::hasHouses($target['key']) && ! Sections::hasHouses($entry->section);

        if ($house === null && $movesIntoHouses) {
            $house = $this->houses->only($ownerId);
        }

        $changes = $this->changes($request, $entry, $target, $house);

        if ($changes === []) {
            return 'Nothing to change — tell me what to correct (text, amount, date, section, house or tags).';
        }

        try {
            $entry->update($changes);
        } catch (Throwable $e) {
            report($e);

            return 'Could not save that change right now. Please try again shortly.';
        }

        $entry->refresh();

        $where = $entry->house === null ? $target['label'] : $target['label'].' · «'.$entry->house->name.'»';
        $money = $entry->amount !== null ? ', '.$entry->amount.' so\'m' : '';
        $updated = "Updated entry #{$entry->id}: {$where}, {$entry->occurred_at->toDateString()}{$money} — {$entry->body}";

        if (! $movesIntoHouses || $entry->house_id !== null || $this->houses->all($ownerId)->isEmpty()) {
            return $updated;
        }

        return $updated.'. It is filed under no house for now. The owner\'s houses: '
            .$this->houses->describe($ownerId).'. Ask which house it belongs to, then set it with this tool.';
    }

    /**
     * Only the fields the model actually sent, normalized.
     *
     * @param  SectionConfig  $target
     * @return array<string, mixed>
     */
    private function changes(Request $request, Entry $entry, array $target, ?House $house): array
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

        // Likewise only a section split by house can keep one, and the house
        // only changes when the model named one.
        if (! Sections::hasHouses($target['key'])) {
            if ($entry->house_id !== null) {
                $changes['house_id'] = null;
            }
        } elseif ($house !== null && $house->id !== $entry->house_id) {
            $changes['house_id'] = $house->id;
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
