<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Provision (or update) the single owner account. This app has no public
 * registration; the one user is created here.
 */
class CreateOwnerCommand extends Command
{
    protected $signature = 'app:create-owner
        {--name= : Display name}
        {--email= : Login email}
        {--password= : Password (prompted securely if omitted)}';

    protected $description = 'Create or update the single owner login account';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $plain = $this->option('password') ?: password('Password', required: true);

        try {
            $this->validate($email, $plain);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($plain)],
        );

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." owner account: {$email}");

        return self::SUCCESS;
    }

    private function validate(string $email, string $password): void
    {
        validator(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'string', Password::default()],
            ],
        )->validate();
    }
}
