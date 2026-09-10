<?php

use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

it('boots the application', function () {
    $this->get('/up')->assertOk();
});

it('loads the POS shell', function () {
    $this->withoutVite();

    $this->get('/pos')
        ->assertOk()
        ->assertSee('id="pos-app"', false);
});

it('loads both Filament panel login pages', function () {
    $this->get('/platform/login')->assertOk();
    $this->get('/admin/login')->assertOk();
});

it('authenticates authorized users into their Filament panels', function () {
    $user = User::factory()->create();
    $platformUser = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);

    $this->actingAs($platformUser)->get('/platform')->assertOk();
    $this->actingAs($user)->get('/admin')->assertOk();
});

it('connects to PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::selectOne('select 1 as connected')->connected)->toBe(1);
});

it('connects to Redis', function () {
    expect((string) Redis::connection()->ping())->toBe('PONG');
});
