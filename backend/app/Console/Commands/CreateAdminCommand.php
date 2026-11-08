<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an administrator account for the monitoring system';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Administrator name'));
        $email = strtolower(trim((string) $this->ask('Administrator email')));
        $password = (string) $this->secret('Password (minimum 12 characters)');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $this->error('Provide a name, valid email, and password with at least 12 characters.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with that email already exists.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->info('Administrator account created.');

        return self::SUCCESS;
    }
}
