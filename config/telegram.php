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
    | Allowed Chat ID
    |--------------------------------------------------------------------------
    |
    | The single Telegram chat_id permitted to use this bot. It lives in the
    | environment (not the database) so it can never be changed through the
    | application itself. Messages from any other chat_id are dropped.
    |
    */

    'allowed_chat_id' => env('TELEGRAM_ALLOWED_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Telegram API Base URL
    |--------------------------------------------------------------------------
    */

    'api_url' => rtrim((string) env('TELEGRAM_API_URL', 'https://api.telegram.org'), '/'),

];
