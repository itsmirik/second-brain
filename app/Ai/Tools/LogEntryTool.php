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
 * Lets the second brain persist a free-text entry into one of the owner's life
 * sections (see config/dashboard.php). Without this tool the model can only
 * acknowledge a message — it cannot actually store anything, so "I logged it"
 * would be a lie. Use it whenever the owner states a fact worth keeping: a
 * note, an expense, income, a health observation, a charity donation.
 */
final class LogEntryTool implements Tool
{
    public function __construct(private readonly OwnerResolver $owner) {}

    public function description(): string
    {
        $sections = implode(', ', Sections::entryKeys());

        return <<<TEXT
        Save a note or record into the owner's journal so it appears on the
        dashboard and can be found later. Call this whenever the owner tells you
        something to remember or log (an expense, income, a note, a health
        entry, a charity donation) — do NOT claim you saved something without
        calling this tool.

        Sections: {$sections}.
        - budget: household money. Use amount, and SIGN it: income is positive,
          an expense is NEGATIVE (e.g. spent 80000 -> amount -80000).
        - home-business: side-venture money, same signing rule as budget.
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

        try {
            $entry = Entry::query()->create([
                'user_id' => $ownerId,
                'section' => $meta['key'],
                'body' => $body,
                'amount' => $meta['money'] ? $this->normalizeAmount($request['amount'] ?? null) : null,
                'occurred_at' => $this->normalizeDate($request['occurred_at'] ?? null),
                'tags' => $this->normalizeTags($request['tags'] ?? null),
            ]);
        } catch (Throwable $e) {
            report($e);

            return 'Could not save that entry right now. Please try again shortly.';
        }

        $when = $entry->occurred_at->toDateString();
        $money = $entry->amount !== null ? ' ('.$entry->amount.' so\'m)' : '';

        return "Saved to {$meta['label']} on {$when}{$money}. It is now on the dashboard.";
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
