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
 * Lets the second brain persist a free-text entry into one of the owner's life
 * sections (see config/dashboard.php). Without this tool the model can only
 * acknowledge a message — it cannot actually store anything, so "I logged it"
 * would be a lie. Use it whenever the owner states a fact worth keeping: a
 * note, an expense, income, a health observation, a charity donation.
 *
 * Home-business entries also go under one of the owner's houses. When the
 * house is unclear the entry is still saved — the money is never dropped —
 * and the reply tells the model to ask which house and fix it.
 */
final class LogEntryTool implements Tool
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
        Save a note or record into the owner's journal so it appears on the
        dashboard and can be found later. Call this whenever the owner tells you
        something to remember or log (an expense, income, a note, a health
        entry, a charity donation) — do NOT claim you saved something without
        calling this tool.

        Sections: {$sections}.
        - budget: household money. Use amount, and SIGN it: income is positive,
          an expense is NEGATIVE (e.g. spent 80000 -> amount -80000).
        - home-business: money of the owner's houses, same signing rule as
          budget. Every entry belongs to one house: pass "house" with its name
          exactly as listed here. The owner's houses: {$houses}.
        - charity: sadaqa / donations the owner GAVE. Use a positive amount.
        - health: health journal (no amount).
        - family: family matters, relatives, household life (no amount).
        - personal: notes, ideas, reminders (no amount).

        Pick the closest section and state which one you chose in your reply. If
        it is genuinely unclear which section fits, ask the owner first instead
        of guessing. Charity/sadaqa giving goes in "charity", never "family".
        TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()
                ->description('Which section to file this under.')
                ->enum(Sections::entryKeys())
                ->required(),
            'body' => $schema->string()
                ->description('The entry text, in the owner\'s own words.')
                ->required(),
            'amount' => $schema->number()
                ->description('Money amount in so\'m. Only for budget / home-business.'),
            'house' => $schema->string()
                ->description('home-business only: the house this is for, by its name as listed.'),
            'occurred_at' => $schema->string()
                ->description('Date of the event, YYYY-MM-DD. Defaults to today.'),
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Optional short labels, e.g. ["sadaqa","mosque"].'),
        ];
    }

    public function handle(Request $request): string
    {
        $sectionKey = (string) ($request['section'] ?? '');
        $meta = Sections::entrySection($sectionKey);

        if ($meta === null) {
            return 'Could not log that: "'.$sectionKey.'" is not a valid section.';
        }

        $body = trim((string) ($request['body'] ?? ''));

        if ($body === '') {
            return 'Could not log that: the entry text was empty.';
        }

        $ownerId = $this->owner->id();

        if ($ownerId === null) {
            return 'Could not log that: no owner account is configured.';
        }

        $hasHouses = Sections::hasHouses($meta['key']);
        $houseName = $this->asName($request['house'] ?? null);
        $house = $hasHouses ? $this->house($ownerId, $houseName) : null;

        try {
            $entry = Entry::query()->create([
                'user_id' => $ownerId,
                'section' => $meta['key'],
                'house_id' => $house?->id,
                'body' => $body,
                'amount' => $meta['money'] ? $this->normalizeAmount($request['amount'] ?? null) : null,
                'occurred_at' => $this->normalizeDate($request['occurred_at'] ?? null),
                'tags' => $this->normalizeTags($request['tags'] ?? null),
            ]);
        } catch (Throwable $e) {
            report($e);

            return 'Could not save that entry right now. Please try again shortly.';
        }

        $where = $house === null ? $meta['label'] : $meta['label'].' · «'.$house->name.'»';
        $when = $entry->occurred_at->toDateString();
        $money = $entry->amount !== null ? ' ('.$entry->amount.' so\'m)' : '';
        $saved = "Saved to {$where} on {$when}{$money}. It is now on the dashboard.";

        if (! $hasHouses || $house !== null) {
            return $saved;
        }

        return $saved.$this->missingHouse($ownerId, $entry, $houseName);
    }

    /**
     * The house the model named, or the owner's only house when it named
     * none. Null when that leaves it unclear.
     */
    private function house(int $ownerId, ?string $name): ?House
    {
        return $name === null
            ? $this->houses->only($ownerId)
            : $this->houses->find($ownerId, $name);
    }

    /**
     * What the model must do about an entry that was saved without a house.
     * Nothing, if the owner keeps no houses and none was named.
     */
    private function missingHouse(int $ownerId, Entry $entry, ?string $name): string
    {
        if ($name === null && $this->houses->all($ownerId)->isEmpty()) {
            return '';
        }

        $reason = $name === null
            ? 'No house was given'
            : 'No single house matches "'.$name.'"';

        return " {$reason}, so it is filed under no house for now. The owner's houses: "
            .$this->houses->describe($ownerId).'. Ask the owner which house it belongs to, then set'
            ." it with the update tool on entry #{$entry->id}. If it is a new house they started,"
            .' add the house first.';
    }

    private function asName(mixed $name): ?string
    {
        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private function normalizeAmount(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (! is_numeric($amount)) {
            return null;
        }

        return (string) $amount;
    }

    private function normalizeDate(mixed $date): string
    {
        if (is_string($date) && $date !== '') {
            try {
                return Date::parse($date)->toDateString();
            } catch (Throwable) {
                // Fall through to today on an unparseable date.
            }
        }

        return Date::today()->toDateString();
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

        return $clean === [] ? null : $clean;
    }
}
