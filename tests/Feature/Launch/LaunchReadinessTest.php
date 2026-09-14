<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('can promote an existing user as the first platform administrator', function () {
    $user = User::factory()->create(['email' => 'operator@example.test']);

    $this->artisan('app:platform-admin', ['email' => $user->email])
        ->expectsOutputToContain('endi /platform sahifasiga kira oladi')
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

it('redirects legacy local panel paths to the organization admin panel', function () {
    $this->get('/panel')
        ->assertRedirect('/admin');
});

it('uses the canonical plan feature pivot table', function () {
    expect(Schema::hasTable('plan_features'))->toBeTrue()
        ->and(Schema::hasTable('plan_feature'))->toBeFalse();
});

it('does not install the removed audit storage', function () {
    expect(Schema::hasTable('audit_logs'))->toBeFalse();
});

it('isolates permission caches between application environments', function () {
    expect(config('permission.cache.key'))->toEndWith('.testing');
});
