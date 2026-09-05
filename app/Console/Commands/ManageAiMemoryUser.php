<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates or updates a panel operator. There is no sign-up page on purpose:
 * this app exposes another product's memory, so the only way in is an account
 * an operator created from a shell on the host.
 *
 *     php artisan aimemory:user you@example.com --name="Your Name"
 *
 * The password is asked for interactively (never a shell argument, which would
 * land in the shell history); pass --password only for non-interactive setup.
 */
class ManageAiMemoryUser extends Command
{
    protected $signature = 'aimemory:user
                            {email : E-mail address that identifies the account}
                            {--name= : Display name (defaults to the local part of the e-mail)}
                            {--password= : Password; prompted for when omitted}';

    protected $description = 'Create or update a panel operator account';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'password' => ['required', Password::min(12)],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $this->option('name') ?: ($existing->name ?? strstr($email, '@', true)),
                'password' => Hash::make($password),
            ]
        );

        $this->info(($existing ? 'Updated' : 'Created').' operator '.$user->email.'.');

        return self::SUCCESS;
    }
}
