<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTransmittalAdministrator extends Command
{
    protected $signature = 'ordo:admin:create {email} {--name=}';

    protected $description = 'Create an administrator account for the protected review actions';

    public function handle(): int
    {
        $email = filter_var($this->argument('email'), FILTER_VALIDATE_EMAIL);
        if (! $email) {
            $this->components->error('Provide a valid administrator email address.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error('A user with that email address already exists.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Administrator name');
        if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 120) {
            $this->components->error('Administrator name is required and must be 120 characters or fewer.');

            return self::FAILURE;
        }

        $password = $this->secret('Administrator password (12 characters minimum)');
        $confirmation = $this->secret('Confirm administrator password');
        if (! is_string($password) || mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->components->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => trim($name),
            'email' => $email,
            'password' => Hash::make($password),
            'transmittal_role' => 'administrator',
        ]);

        $this->components->info("Administrator account created for {$email}.");

        return self::SUCCESS;
    }
}
