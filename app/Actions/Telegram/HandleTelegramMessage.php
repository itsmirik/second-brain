<?php

declare(strict_types=1);

namespace App\Actions\Telegram;

use App\Ai\Agents\SecondBrainAgent;
use App\Ai\Support\OwnerResolver;
use App\Models\ActivityLog;
use App\Services\Telegram\TelegramClient;
use App\Telegram\TelegramUpdate;
use Illuminate\Support\Arr;
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
    /**
     * Sent the moment the job starts and later edited into the real answer.
     * An agent turn can take many seconds (model + tool calls), and without
     * this the owner cannot tell a thinking bot from a dead one.
     */
    public const PLACEHOLDER = '⏳ …';

    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly OwnerResolver $owner,
    ) {}

    public function __invoke(TelegramUpdate $update): void
    {
        if ($update->chatId === null) {
            return;
        }

        // Only work that actually goes to the agent is slow enough to need a
        // loader; canned replies are instant.
        $placeholderId = $this->text($update) === null
            ? null
            : $this->showLoader($update->chatId);

        $reply = $this->generateReply($update);

        $this->deliver($update->chatId, $placeholderId, $reply);

        ActivityLog::query()->create([
            'direction' => 'out',
            'telegram_update_id' => null,
            'chat_id' => (string) $update->chatId,
            'message_type' => 'reply',
            'payload_summary' => "reply to update {$update->updateId} (".mb_strlen($reply).' chars)',
        ]);
    }

    /**
     * The agent, carrying the owner's conversation history. Telegram has no
     * session, so without this every message would arrive with no memory of
     * the previous one — and a clarifying question could never be answered.
     */
    private function agent(): SecondBrainAgent
    {
        $agent = SecondBrainAgent::make();
        $owner = $this->owner->resolve();

        return $owner === null ? $agent : $agent->continueLastConversation($owner);
    }

    /**
     * Put the placeholder on screen and return its message id, or null if
     * Telegram would not take it — a broken loader must never cost the owner
     * their answer.
     */
    private function showLoader(int $chatId): ?int
    {
        $messageId = null;

        try {
            $sent = $this->telegram->sendMessage($chatId, self::PLACEHOLDER);
            $id = Arr::get($sent, 'result.message_id');
            $messageId = is_numeric($id) ? (int) $id : null;

            $this->telegram->sendChatAction($chatId, 'typing');
        } catch (Throwable $e) {
            report($e);
        }

        return $messageId;
    }

    /**
     * Turn the placeholder into the answer, or send the answer on its own if
     * there is no placeholder to edit.
     */
    private function deliver(int $chatId, ?int $placeholderId, string $reply): void
    {
        if ($placeholderId !== null) {
            try {
                $this->telegram->editMessageText($chatId, $placeholderId, $reply);

                return;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->telegram->sendMessage($chatId, $reply);
    }

    private function text(TelegramUpdate $update): ?string
    {
        if ($update->text === null || trim($update->text) === '') {
            return null;
        }

        return trim($update->text);
    }

    private function generateReply(TelegramUpdate $update): string
    {
        $text = $this->text($update);

        if ($text === null) {
            return 'Got it — I can only read text for now. Photo and document handling is coming in a later phase.';
        }

        try {
            return $this->agent()
                ->prompt($text)
                ->text;
        } catch (Throwable $e) {
            // Every configured provider failed (or misconfigured). Never leave
            // the owner without a reply, and never crash the queue job.
            report($e);

            return 'Sorry — the assistant is temporarily unavailable. Please try again shortly.';
        }
    }
}
