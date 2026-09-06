<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;
use Throwable;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook
        {url? : Full HTTPS URL to the webhook (defaults to APP_URL + route)}';

    protected $description = 'Register the Telegram webhook URL and secret token with the Bot API';

    public function handle(TelegramClient $telegram): int
    {
        $secret = (string) config('telegram.webhook_secret');

        if ($secret === '') {
            $this->components->error('TELEGRAM_WEBHOOK_SECRET is empty. Set it in .env first.');
            $this->line('  Generate one with: <info>openssl rand -hex 32</info>');

            return self::FAILURE;
        }

        $url = $this->argument('url') ?? route('telegram.webhook');

        if (! str_starts_with($url, 'https://')) {
            $this->components->error("Webhook URL must be HTTPS. Got: {$url}");

            return self::FAILURE;
        }

        try {
            $telegram->setWebhook($url, $secret);
        } catch (Throwable $e) {
            $this->components->error('setWebhook failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Webhook set to {$url}");

        return self::SUCCESS;
    }
}
