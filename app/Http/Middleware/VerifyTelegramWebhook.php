<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transport-level gate for the Telegram webhook.
 *
 * Verifies the `X-Telegram-Bot-Api-Secret-Token` header (set via setWebhook)
 * against our configured secret, and enforces HTTPS outside local/testing.
 * Every failure returns 404 — we never confirm to a stranger that the
 * endpoint (or the bot) exists.
 */
class VerifyTelegramWebhook
{
    private const HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'testing']) && ! $request->secure()) {
            $this->deny('non-HTTPS request');
        }

        $expected = (string) config('telegram.webhook_secret');
        $provided = (string) $request->header(self::HEADER, '');

        // Fail closed if the secret was never configured.
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            $this->deny('secret token mismatch');
        }

        return $next($request);
    }

    private function deny(string $reason): never
    {
        Log::warning('Telegram webhook rejected', ['reason' => $reason]);

        abort(404);
    }
}
