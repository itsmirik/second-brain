<?php

declare(strict_types=1);

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telegram Webhook Route
|--------------------------------------------------------------------------
|
| The single internet-facing endpoint of this application. It is registered
| outside the `web` middleware group (no session, no CSRF, no Inertia). The
| enclosing group in bootstrap/app.php applies `throttle:telegram`; here we
| add secret-token verification. Access control by chat_id happens inside
| the controller, after the payload is parsed.
|
*/

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware(VerifyTelegramWebhook::class)
    ->name('telegram.webhook');
