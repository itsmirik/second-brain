<?php

declare(strict_types=1);

namespace App\Actions\Telegram;

use App\Ai\Agents\SecondBrainAgent;
use App\Models\ActivityLog;
use App\Services\Telegram\TelegramClient;
use App\Telegram\TelegramUpdate;
use Throwable;

/**
 * Turn one inbound message into a reply and send it.
 *
 * Phase 1 scope: prompt the second brain and reply. Later phases will branch
 * here on classified intent (log a transaction, store a note, answer a
 * cross-business question, handle an uploaded document, ...).
 */
final class HandleTelegramMessage
{
    public function __construct(
        private readonly TelegramClient $telegram,
    ) {}

    public function __invoke(TelegramUpdate $update): void
    {
        if ($update->chatId === null) {
            return;
        }

        $reply = $this->generateReply($update);

        $this->telegram->sendMessage($update->chatId, $reply);

        ActivityLog::query()->create([
            'direction' => 'out',
            'telegram_update_id' => null,
            'chat_id' => (string) $update->chatId,
            'message_type' => 'reply',
            'payload_summary' => "reply to update {$update->updateId} (".mb_strlen($reply).' chars)',
        ]);
    }

    private function generateReply(TelegramUpdate $update): string
    {
        if ($update->text === null || trim($update->text) === '') {
            return 'Got it — I can only read text for now. Photo and document handling is coming in a later phase.';
        }

        try {
            return SecondBrainAgent::make()
                ->prompt($update->text)
                ->text;
        } catch (Throwable $e) {
            // Every configured provider failed (or misconfigured). Never leave
            // the owner without a reply, and never crash the queue job.
            report($e);

            return 'Sorry — the assistant is temporarily unavailable. Please try again shortly.';
        }
    }
}
