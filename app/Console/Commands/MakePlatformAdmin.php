<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class MakePlatformAdmin extends Command
{
    protected $signature = 'app:platform-admin {email : Platforma administratorining elektron pochta manzili}';

    protected $description = 'OsonPOS platformasining dastlabki administratorini yaratish yoki tayinlash';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => text('Ism', required: true),
                'email' => $email,
                'password' => password('Parol', required: true, validate: ['password' => 'min:8']),
            ]);
        }

        $user->is_platform_admin = true;
        $user->save();

        $this->components->info("{$user->email} endi /platform sahifasiga kira oladi.");

        return self::SUCCESS;
    }
}
