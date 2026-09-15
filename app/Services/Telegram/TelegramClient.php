<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Telegram Bot API client built on Laravel's HTTP client.
 *
 * We deliberately avoid a third-party SDK here: the surface we need is tiny
 * and raw HTTP keeps the dependency footprint (and the audit surface) small.
 */
class TelegramClient
{
    public function __construct(
        private readonly ?string $token,
        private readonly string $apiUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            token: config('telegram.bot_token'),
            apiUrl: config('telegram.api_url', 'https://api.telegram.org'),
        );
    }

    /**
     * Send a text message to a chat. Returns the decoded Telegram response.
     *
     * @return array<string, mixed>
     */
    public function sendMessage(int|string $chatId, string $text): array
    {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    /**
     * Replace the text of a message the bot already sent. Used to turn the
     * "working on it" placeholder into the real answer, so a slow reply costs
     * the chat one message instead of two.
     *
     * @return array<string, mixed>
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text): array
    {
        return $this->call('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    /**
     * Show Telegram's own "typing..." indicator. It clears itself after a few
     * seconds, so it complements the placeholder message rather than replacing
     * it.
     *
     * @return array<string, mixed>
     */
    public function sendChatAction(int|string $chatId, string $action = 'typing'): array
    {
        return $this->call('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    /**
     * Register the webhook URL with a secret token.
     *
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'max_connections' => 10,
            'drop_pending_updates' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function call(string $method, array $parameters = []): array
    {
        if (empty($this->token)) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        $response = $this->request($method, $parameters);

        /** @var array<string, mixed> $decoded */
        $decoded = $response->json() ?? [];

        if ($response->failed() || ($decoded['ok'] ?? false) !== true) {
            throw new RuntimeException(sprintf(
                'Telegram API call [%s] failed: %s',
                $method,
                $decoded['description'] ?? $response->status(),
            ));
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function request(string $method, array $parameters): Response
    {
        return Http::asJson()
            ->timeout(15)
            ->retry(2, 200, throw: false)
            ->post("{$this->apiUrl}/bot{$this->token}/{$method}", $parameters);
    }
}
