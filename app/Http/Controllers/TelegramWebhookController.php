<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\ActivityLog;
use App\Telegram\TelegramUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;

/**
 * The one internet-facing entry point.
 *
 * Responsibilities are kept deliberately thin so Telegram is acknowledged in
 * milliseconds: parse, enforce single-owner access control, dedupe on
 * update_id, then hand the real work to a queued job. It never calls the AI
 * or Telegram send API synchronously.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $update = TelegramUpdate::fromArray($payload);

        // Malformed / missing update_id: acknowledge so Telegram stops retrying,
        // but do nothing else. We cannot dedupe without an update_id.
        if ($update->updateId <= 0) {
            return $this->ack();
        }

        // Single-owner access control. Anything not from the configured chat_id
        // is dropped silently (no reply) and audited. Because the secret-token
        // gate already ran, reaching here means the caller knew the secret, so
        // an audit row is meaningful, not attacker-controllable spam.
        if (! $update->isFromChat(config('telegram.allowed_chat_id'))) {
            $this->log('in', $update->updateId, $update->chatId, 'rejected', 'unauthorized chat_id');

            return $this->ack();
        }

        // Idempotency: the unique index on telegram_update_id makes this a no-op
        // on a retried delivery. insertOrIgnore returns the number of rows
        // actually inserted, so 0 means "already seen — do not process again".
        $inserted = ActivityLog::query()->insertOrIgnore([
            'direction' => 'in',
            'telegram_update_id' => $update->updateId,
            'chat_id' => (string) $update->chatId,
            'message_type' => $update->type,
            'payload_summary' => $this->summarize($update),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ]);

        if ($inserted === 0) {
            return $this->ack();
        }

        ProcessTelegramUpdate::dispatch($update->raw);

        return $this->ack();
    }

    private function ack(): Response
    {
        // 200 with empty body: Telegram treats this as delivered.
        return response()->noContent(Response::HTTP_OK);
    }

    private function summarize(TelegramUpdate $update): string
    {
        // Intentionally metadata only — we never copy potentially sensitive
        // message content into the audit log.
        $length = $update->text !== null ? mb_strlen($update->text) : 0;

        return "{$update->type} ({$length} chars)";
    }

    private function log(string $direction, ?int $updateId, ?int $chatId, string $type, string $summary): void
    {
        ActivityLog::query()->insertOrIgnore([
            'direction' => $direction,
            'telegram_update_id' => $updateId,
            'chat_id' => $chatId !== null ? (string) $chatId : null,
            'message_type' => $type,
            'payload_summary' => $summary,
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ]);
    }
}
