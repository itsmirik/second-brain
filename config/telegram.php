<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bot Token
    |--------------------------------------------------------------------------
    |
    | The bot token issued by @BotFather. Never commit this value.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret Token
    |--------------------------------------------------------------------------
    |
    | Sent to Telegram via setWebhook and returned by Telegram in the
    | `X-Telegram-Bot-Api-Secret-Token` header on every webhook request. We
    | reject any request whose header does not match this value.
    |
    */

    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Chat IDs
    |--------------------------------------------------------------------------
    |
    | The Telegram chat_ids permitted to use this bot, comma-separated. These
    | are the owner's own accounts/devices — every one of them speaks to the
    | same single-owner journal. The list lives in the environment (not the
    | database) so it can never be changed through the application itself.
    | Messages from any other chat_id are dropped. An empty list fails closed.
    |
    */

    'allowed_chat_ids' => array_values(array_filter(
        array_map(trim(...), explode(',', (string) env('TELEGRAM_ALLOWED_CHAT_IDS', ''))),
        static fn (string $id): bool => $id !== '',
    )),

    /*
    |--------------------------------------------------------------------------
    | Telegram API Base URL
    |--------------------------------------------------------------------------
    */

    'api_url' => rtrim((string) env('TELEGRAM_API_URL', 'https://api.telegram.org'), '/'),

];
