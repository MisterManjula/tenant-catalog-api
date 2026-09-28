<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Prints one Sanctum token per user, for pasting into the curl examples in the README.
 *
 * Revokes each user's existing tokens first, so running this command twice does not
 * pile up unused tokens.
 */
class PrintDemoTokens extends Command
{
    protected $signature = 'app:demo-tokens';

    protected $description = 'Print a fresh Sanctum token for every user';

    public function handle(): int
    {
        $users = User::query()->with('tenant')->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->components->warn('No users found. Run `php artisan db:seed` first.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Tenant', 'Token');

        $users->each(function (User $user): void {
            $tenant = $user->tenant;

            if (! $tenant) {
                $this->components->warn("User {$user->id} ({$user->email}) has no tenant, skipping.");

                return;
            }

            $user->tokens()->delete();
            $token = $user->createToken('demo')->plainTextToken;

            $this->components->twoColumnDetail("{$tenant->name} ({$user->email})", $token);
        });

        return self::SUCCESS;
    }
}
