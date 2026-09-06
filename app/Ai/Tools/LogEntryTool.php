<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Entry;
use App\Models\User;
use App\Support\Dashboard\Sections;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
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
        - budget: household income/expenses (use amount).
        - home-business: side-venture money (use amount).
        - health: health journal (no amount).
        - family: family matters and charity/sadaqa giving (no amount).
        - personal: notes, ideas, reminders (no amount).

        Pick the closest section. Charity/sadaqa goes in family. Put the money
        value in "amount" (numeric, so'm) only for budget and home-business.
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

        $user = $this->resolveOwner();

        if ($user === null) {
            return 'Could not log that: no owner account is configured.';
        }

        try {
            $entry = Entry::query()->create([
                'user_id' => $user->getAuthIdentifier(),
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

    private function resolveOwner(): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user;
        }

        $email = config('dashboard.owner_email');

        if (is_string($email) && $email !== '') {
            $owner = User::query()->where('email', $email)->first();

            if ($owner !== null) {
                return $owner;
            }
        }

        return User::query()->orderByDesc('id')->first();
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
