# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A private, **single-owner** "second brain": a Laravel 13 + Inertia/Vue 3 dashboard plus a
Telegram bot, both driven by one AI agent. The owner logs facts in free-text Russian or Uzbek
(via Telegram or web chat) and the agent files them into life sections; the dashboard
shows those entries, live figures from an external ERP (Atheer), period money reports,
and a charity (sadaqa) obligation calculation.

There is no public registration and no multi-tenancy. The one user is created with
`php artisan app:create-owner`. UI language is Russian (`APP_LOCALE=ru`) — section labels,
error strings and `format.ts` are hardcoded Russian, not translation keys. The **agent**
is the exception: its prompt tells it to mirror whatever language the owner wrote in
(Russian or Uzbek). Code, comments, and commits are English.

## Commands

```bash
composer setup        # install deps, .env, key, migrate, npm install, build
composer dev          # php artisan dev → serve + queue:listen + pail + vite (all four)
composer ci:check     # what CI runs: vue-tsc, then composer test
composer test         # config:clear + phpstan + php artisan test
composer types:check  # phpstan (larastan level 7 over app/ bootstrap/ config/ database/ routes/)
npm run types:check   # vue-tsc --noEmit

php artisan test --filter=SomeTest          # single test / method
php artisan test tests/Feature/FooTest.php   # single file
php artisan telegram:set-webhook [url]      # register webhook + secret with the Bot API
php artisan app:create-owner                # create/update the sole login
```

Queue is `database` — a queue worker must be running or Telegram messages are never
answered. `composer dev` starts one.

## Architecture

### Two entry points, one agent

- **Web** (`routes/web.php`): everything behind `auth` session middleware. Inertia pages.
- **Telegram** (`routes/telegram.php`): the *only* unauthenticated internet-facing route,
  registered in `bootstrap/app.php` **outside** the `web` group (no session/CSRF/Inertia)
  with `throttle:telegram`.

The webhook path is deliberately layered, and each layer re-checks:

```
VerifyTelegramWebhook (X-Telegram-Bot-Api-Secret-Token)
  → TelegramWebhookController  (parse, drop non-owner chat_id, dedupe on update_id
                                via insertOrIgnore on activity_logs, ack in ms)
    → ProcessTelegramUpdate (queued, tries=3, backoff 5/15/30; re-verifies chat_id)
      → HandleTelegramMessage (placeholder "⏳", prompt agent, edit placeholder
                              into the reply, log 'out' row)
```

`TELEGRAM_ALLOWED_CHAT_IDS` (comma-separated) lives in env, never the DB, so it cannot
be changed through the app, and an empty list fails closed. `activity_logs` stores **metadata only** — never message content.

### The agent (`app/Ai/`)

`SecondBrainAgent` (laravel/ai) is the single brain used by both the bot and the web chat.
Application code never touches a vendor SDK — it prompts this agent. `provider()` returns
an Anthropic → OpenAI → Gemini failover list filtered to providers that actually have a key,
ordered so `AI_DEFAULT_PROVIDER` goes first; an empty list surfaces a clear error.

`instructions()` is rebuilt per prompt so it can state **today's date and timezone** — the
model must never guess a date or ask the owner what day it is.

Tools live in `app/Ai/Tools/` and are the only way the model can *do* anything. Reading
matters as much as writing: without the read tools the bot answers "I can't look that up,
check the dashboard", which defeats the product.

- `SearchEntriesTool` — **read**. Filters the journal by sections, a relative period or
  explicit dates, and free text; returns matching entries (with ids) plus per-section and
  overall totals, so the model never does arithmetic itself.
- `MoneyReportTool` — **read**. `MoneyReporter` for a window: per-source income/expense/net
  across Atheer + money sections, the grand net, and charity given.
- `CharityStatusTool` — **read**. `CharityService` rows: profit, percentage, obligation,
  given, remaining, newest month first.
- `AtheerReportsTool` — **read**. Live ERP reports, raw JSON for the model to phrase.
- `LogEntryTool` — **write**. Creates an `Entry`. Its description encodes the sign
  convention and section routing; the prompt forbids claiming a save without calling it.
- `UpdateEntryTool` — **write**. Corrects a saved entry by id (text, amount, date, tags, or
  moves it between sections); moving into a non-money section clears the amount.

`App\Ai\Support\OwnerResolver` answers "whose journal is this?" for every tool — the
logged-in user, else `BRAIN_OWNER_EMAIL`, else the only account. Telegram has no session,
so tools must never rely on `Auth`. `App\Ai\Support\ToolResponse::json()` is the single
JSON encoding for tool output (unescaped Cyrillic, preserved zero fractions).

An agent turn takes seconds (model + tools), so `HandleTelegramMessage` posts a `PLACEHOLDER`
message plus a `typing` chat action first and then **edits that message** into the answer —
one message in the chat, not two. If the placeholder or the edit fails, the answer is still
sent as a fresh message; a broken loader must never cost the owner their reply. The web chat
has its own «Думаю…» bubble in `Chat.vue`.

Conversation memory: the web chat continues the owner's latest conversation, and
`HandleTelegramMessage` does the same via `continueLastConversation($owner)` — otherwise
every Telegram message would arrive with no memory and a clarifying question could never
be answered.

Agent/tool stubs are in `stubs/` (`php artisan make:agent`, `make:tool`).

### Sections are config-driven

`config/dashboard.php` is the source of truth for the owner's life sections. Each has
`driver` (`entries` = generic log in the `entries` table, `atheer` = external ERP),
`money` (does it carry amounts), `status` (`live` | `planned`).

`App\Support\Dashboard\Sections` is the accessor. **`routes/web.php` registers routes in a
loop over `Sections::entryKeys()`**, and `HandleInertiaRequests` shares the section list
globally so the sidebar renders everywhere. Adding a live entries section = add a config
entry; no controller changes. `charity` is the exception: it is entries-backed but has its
own `CharityController` (monthly obligation maths) and is skipped in that loop.

### Money

Sign convention, applied everywhere: **income positive, expense negative**. The web form
takes a positive amount + `direction` and `SectionController::signedAmount()` signs it;
`LogEntryTool` expects the model to send it already signed.

`App\Support\Money\MoneyReporter` is the one place that nets everything — Atheer ERP plus
the money sections (`budget`, `home-business`) — into per-source income/expense/net plus a
grand net. `Reports` uses it directly; `CharityService` uses its `profit()` as the base for
`profit × percentage`. Charity giving is deliberately **not** in the profit base (obligation
is computed on profit *before* giving) and lives in its own section.

`App\Support\Reports\Period` turns a period type (day…year) + anchor date into `[from, to]`,
a Russian label, and prev/next anchors, so neither controllers nor the UI do date maths.
`App\Support\Reports\Range` is its conversational counterpart: it turns a keyword the agent
picks (`last_month`, `this_week`, `all`, …) or explicit dates into `[from, to]`, so the model
never does calendar maths — which it gets wrong.

Charity percentage and manual monthly profit overrides are rows in the generic per-user
`settings` key/value table (`charity.percentage`, `charity.profit.YYYY-MM`) via
`Setting::get()/put()`.

### Degradation

The Atheer ERP is a separate co-deployed app reached over HTTP (`AtheerApiClient`, cached
`ATHEER_CACHE_TTL` seconds). Every consumer must keep working when it is down: pages render
with an `error` prop instead of throwing, `MoneyReporter` reports the source as
`available: false` with zeros, the tool returns a plain "temporarily unavailable" string,
and `HandleTelegramMessage` always sends *some* reply rather than failing the job.

### Frontend

Inertia + Vue 3 `<script setup>`, Tailwind v4, no UI component library. Pages in
`resources/js/pages/`, single `AppLayout.vue`, shared props are `name`, `auth.user`,
`sections`. Formatting helpers (`money`, `count`, `shortDate` — all `ru-RU`, UZS "сум")
live in `resources/js/lib/format.ts`; use them rather than inlining `Intl`.

`resources/js/actions/`, `resources/js/routes/`, and `resources/js/wayfinder/` are
**generated by Wayfinder** from PHP controllers/routes — never hand-edit them; they are
gitignored and rebuilt on every `npm run build`.

## Conventions

- `declare(strict_types=1);` in all application PHP (the framework-scaffolded files
  predating it are the exception).
- Typed properties, constructor promotion, `readonly` on service/DTO dependencies;
  services are bound as singletons via `fromConfig()` factories in `AppServiceProvider`.
- Dates: `Date::use(CarbonImmutable::class)` is set globally — use the `Date` facade, and
  expect `CarbonImmutable` everywhere.
- Ownership checks are explicit: entry mutations `abort_unless` the `user_id` matches the
  authenticated user.
- Tests live in `tests/Feature/` (Ai tools, Telegram) and `tests/Unit/`; both suites boot the
  app and use sqlite `:memory:`. Agent tools are testable without a provider: call
  `handle(new Laravel\Ai\Tools\Request([...]))` directly, and use `SecondBrainAgent::fake([...])`
  plus `assertPrompted()` when exercising the Telegram/web paths.

## No formatter / linter

Pint and the vite-plus lint/fmt passes were removed deliberately. Nothing reformats your
code, and no style rule will fail CI. Match the surrounding file by hand.

Correctness gates remain and are still enforced: **PHPStan (larastan level 7)** and
**vue-tsc**, both wired into `composer ci:check`.
