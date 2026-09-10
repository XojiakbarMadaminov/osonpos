<?php

use App\Models\User;

it('can promote an existing user as the first platform administrator', function () {
    $user = User::factory()->create(['email' => 'operator@example.test']);

    $this->artisan('app:platform-admin', ['email' => $user->email])
        ->expectsOutputToContain('can now access /platform')
        ->assertSuccessful();

    expect($user->refresh()->is_platform_admin)->toBeTrue();
});

it('ships production deployment and local domain configuration', function () {
    expect(base_path('.env.production.example'))->toBeFile()
        ->and(base_path('deploy/nginx/osonpos.conf'))->toBeFile()
        ->and(base_path('deploy/redis/osonpos.conf'))->toBeFile()
        ->and(base_path('deploy/supervisor/osonpos-worker.conf'))->toBeFile()
        ->and(base_path('bin/backup-postgres'))->toBeFile()
        ->and(base_path('bin/deploy-production'))->toBeFile()
        ->and(config('app.url'))->toBe('http://osonpos.lc');
});
