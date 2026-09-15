<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Answers "whose journal am I working in?" for the agent's tools.
 *
 * The web chat runs inside an authenticated session, the Telegram bot does
 * not — but this is a single-owner brain, so both must end up at the same
 * account. Resolution order: the logged-in user, then the configured owner
 * email, then the only (most recent) account there is.
 */
final class OwnerResolver
{
    public function resolve(): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user;
        }

        $email = config('dashboard.owner_email');

        if (is_string($email) && $email !== '') {
            $owner = User::query()->where('email', $email)->first();

            if ($owner !== null) {
                return $owner;
            }
        }

        return User::query()->orderByDesc('id')->first();
    }

    /**
     * The owner's id, or null when no account exists yet.
     */
    public function id(): ?int
    {
        $owner = $this->resolve();

        return $owner === null ? null : (int) $owner->getAuthIdentifier();
    }
}
