<?php

declare(strict_types=1);

namespace App\Telegram;

use Illuminate\Support\Arr;

/**
 * Immutable, typed view over a raw Telegram update payload.
 *
 * Only the fields Phase 1 needs are surfaced. The raw array is retained so
 * later phases (photos, documents, callbacks) can read additional fields
 * without changing this contract.
 */
final readonly class TelegramUpdate
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $updateId,
        public ?int $chatId,
        public string $type,
        public ?string $text,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        // Telegram nests the actual content under one of several keys.
        $message = $payload['message']
            ?? $payload['edited_message']
            ?? $payload['channel_post']
            ?? $payload['callback_query']['message']
            ?? [];

        $chatId = Arr::get($message, 'chat.id');

        return new self(
            updateId: (int) ($payload['update_id'] ?? 0),
            chatId: is_numeric($chatId) ? (int) $chatId : null,
            type: self::resolveType($payload, $message),
            text: self::resolveText($payload, $message),
            raw: $payload,
        );
    }

    /**
     * Whether this update came from one of the owner's permitted chats.
     *
     * Fails closed: an empty allowlist authorises nobody, so a missing
     * TELEGRAM_ALLOWED_CHAT_IDS can never open the bot to the world.
     *
     * @param  list<int|string>  $allowedChatIds
     */
    public function isFromAllowedChat(array $allowedChatIds): bool
    {
        if ($allowedChatIds === [] || $this->chatId === null) {
            return false;
        }

        foreach ($allowedChatIds as $allowed) {
            if ((string) $this->chatId === (string) $allowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $message
     */
    private static function resolveType(array $payload, array $message): string
    {
        return match (true) {
            isset($payload['callback_query']) => 'callback',
            isset($message['photo']) => 'photo',
            isset($message['document']) => 'document',
            isset($message['voice']) => 'voice',
            isset($message['text']) => 'text',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $message
     */
    private static function resolveText(array $payload, array $message): ?string
    {
        $text = $payload['callback_query']['data']
            ?? $message['text']
            ?? $message['caption']
            ?? null;

        return is_string($text) ? $text : null;
    }
}
