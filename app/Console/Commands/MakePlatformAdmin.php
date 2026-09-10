<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class MakePlatformAdmin extends Command
{
    protected $signature = 'app:platform-admin {email : Platform administrator email address}';

    protected $description = 'Create or promote the initial OsonPOS platform administrator';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => text('Name', required: true),
                'email' => $email,
                'password' => password('Password', required: true, validate: ['password' => 'min:8']),
            ]);
        }

        $user->is_platform_admin = true;
        $user->save();

        $this->components->info("{$user->email} can now access /platform.");

        return self::SUCCESS;
    }
}
