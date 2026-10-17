<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create {email : The owner email address} {--name= : Display name for a new account}';

    protected $description = 'Create the super admin account, or promote an existing user. Sign in at /securegate.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            if ($user->isSuperAdmin()) {
                $this->info("{$email} is already a super admin.");

                return self::SUCCESS;
            }

            if (! $this->confirm("{$email} already has an account. Make it a super admin?")) {
                return self::FAILURE;
            }

            $user->forceFill(['role' => User::ROLE_SUPER_ADMIN])->save();
            AdminAuditLog::record(null, 'admin.promoted', $user);
            $this->info("{$email} is now a super admin. Sign in at /securegate.");

            return self::SUCCESS;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Display name', 'Owner'));

        $password = (string) $this->secret('Password (at least 12 characters)');
        if (mb_strlen($password) < 12) {
            $this->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Repeat the password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['role' => User::ROLE_SUPER_ADMIN])->save();

        AdminAuditLog::record(null, 'admin.created', $user);
        $this->info("Super admin {$email} created. Sign in at /securegate.");

        return self::SUCCESS;
    }
}
