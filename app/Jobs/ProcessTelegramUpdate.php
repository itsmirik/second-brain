<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Telegram\HandleTelegramMessage;
use App\Telegram\TelegramUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processes a single Telegram update off the request path so the webhook can
 * acknowledge Telegram immediately. The raw payload is carried (rather than the
 * DTO) so the job stays trivially serializable.
 */
class ProcessTelegramUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    public function handle(HandleTelegramMessage $handle): void
    {
        $update = TelegramUpdate::fromArray($this->payload);

        // Defence in depth: re-verify the owner even though the controller
        // already did. A job should never act on an unauthorized chat_id.
        if (! $update->isFromAllowedChat(config('telegram.allowed_chat_ids', []))) {
            return;
        }

        $handle($update);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }
}
