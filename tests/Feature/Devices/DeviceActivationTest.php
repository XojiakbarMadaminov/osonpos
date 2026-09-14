<?php

use App\Actions\Devices\GenerateDeviceActivationCode;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Filament\Admin\Resources\Devices\Pages\CreateDevice as CreateDevicePage;
use App\Models\Device;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\DeviceCredential;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Livewire\Livewire;

function activationUser(Organization $organization, Store $store, ?OrganizationRole $role = OrganizationRole::Cashier): User
{
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);

    if ($role) {
        app(OrganizationAuthorization::class)->runForUserInTenant(
            $user,
            $organization,
            fn (User $tenantUser) => $tenantUser->assignRole($role->value),
        );
    }

    return $user;
}

function enablePosForActivation(Organization $organization, bool $active = true, bool $withFeature = true): void
{
    $plan = Plan::factory()->create();
    if ($withFeature) {
        $feature = Feature::factory()->create(['code' => 'pos']);
        $plan->features()->attach($feature);
    }

    Subscription::factory()->for($organization)->for($plan)->create([
        'starts_at' => $active ? now()->subDay() : now()->subMonth(),
        'ends_at' => $active ? now()->addMonth() : now()->subDay(),
    ]);
}

it('allows an owner to create a device in an accessible store from admin', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = activationUser($organization, $store, OrganizationRole::Owner);
    app(TenantContext::class)->resolveFor($owner, $organization->id);

    $this->actingAs($owner)->withSession([
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateDevicePage::class)
        ->assertFormSet(['store_id' => $store->id])
        ->fillForm([
            'store_id' => $store->id,
            'name' => 'Asosiy kassa',
            'code' => 'kassa-01',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $device = Device::query()->sole();
    expect($device->organization_id)->toBe($organization->id)
        ->and($device->store_id)->toBe($store->id)
        ->and($device->code)->toBe('KASSA-01');
});

it('redirects POS guests to login with the intended URL and an Uzbek message', function () {
    $this->get('/pos')
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHas('url.intended', url('/pos'))
        ->assertSessionHas('pos_login_required');

    $this->get('/pos/device-setup')
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHas('url.intended', url('/pos/device-setup'));

    $this->get(route('filament.admin.auth.login'))
        ->assertOk()
        ->assertSee('POS’dan foydalanish uchun avval tizimga kiring.');
});

it('returns an Uzbek 401 response to unauthenticated POS API requests', function () {
    $this->getJson('/api/pos/bootstrap')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'POS’dan foydalanish uchun avval tizimga kiring.');
});

it('redirects an authenticated unpaired browser to setup and loads POS after pairing', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    $session = [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ];

    $this->actingAs($user)->withSession($session)->get('/pos')
        ->assertRedirect(route('pos.device-setup'));

    $credential = app(DeviceCredential::class)->issue($device);
    $this->withCookie(DeviceCredential::COOKIE_NAME, $credential)
        ->get('/pos')
        ->assertOk()
        ->assertSee('id="pos-app"', false);
});

it('activates a device once and stores a long lived browser credential', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    $code = app(GenerateDeviceActivationCode::class)->execute($device);

    $response = $this->actingAs($user)->postJson('/api/pos/devices/activate', [
        'activation_code' => mb_strtolower($code),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $device->id)
        ->assertCookie(DeviceCredential::COOKIE_NAME)
        ->assertSessionHas('current_organization_id', $organization->id)
        ->assertSessionHas('current_store_id', $store->id)
        ->assertSessionHas(DeviceCredential::SESSION_KEY);

    expect($device->refresh()->activation_code_hash)->toBeNull()
        ->and($device->activation_expires_at)->toBeNull()
        ->and($device->credential_hash)->not->toBeNull()
        ->and($device->activated_at)->not->toBeNull();

    $cookie = app(DeviceCredential::class)->cookie('device.secret');
    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addMonths(11)->timestamp);
});

it('rejects expired, invalid, and already used activation codes', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    $code = app(GenerateDeviceActivationCode::class)->execute($device);
    $device->forceFill(['activation_expires_at' => now()->subSecond()])->save();

    $this->actingAs($user)->postJson('/api/pos/devices/activate', [
        'activation_code' => $code,
    ])->assertUnprocessable()->assertJsonValidationErrors('activation_code');

    $this->postJson('/api/pos/devices/activate', [
        'activation_code' => 'BADCODE1',
    ])->assertUnprocessable()->assertJsonValidationErrors('activation_code');

    $code = app(GenerateDeviceActivationCode::class)->execute($device);
    $this->postJson('/api/pos/devices/activate', ['activation_code' => $code])->assertOk();
    $this->postJson('/api/pos/devices/activate', ['activation_code' => $code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('activation_code');
});

it('blocks activation across tenant, store, permission, subscription, and feature boundaries', function (string $boundary) {
    $organization = Organization::factory()->create();
    $allowedStore = Store::factory()->for($organization)->create();
    $deviceStore = $boundary === 'store'
        ? Store::factory()->for($organization)->create()
        : $allowedStore;
    $deviceOrganization = $boundary === 'tenant'
        ? Organization::factory()->create()
        : $organization;
    if ($boundary === 'tenant') {
        $deviceStore = Store::factory()->for($deviceOrganization)->create();
    }

    $user = activationUser($organization, $allowedStore, $boundary === 'permission' ? null : OrganizationRole::Cashier);
    enablePosForActivation(
        $deviceOrganization,
        $boundary !== 'subscription',
        $boundary !== 'feature',
    );
    $device = Device::factory()->for($deviceOrganization)->for($deviceStore)->create();
    $code = app(GenerateDeviceActivationCode::class)->execute($device);

    $this->actingAs($user)->postJson('/api/pos/devices/activate', [
        'activation_code' => $code,
    ])->assertUnprocessable()->assertJsonValidationErrors('activation_code');
})->with(['tenant', 'store', 'permission', 'subscription', 'feature']);

it('restores the device from its browser credential after a new login session', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    $credential = app(DeviceCredential::class)->issue($device);

    $this->actingAs($user)
        ->withCredentials()
        ->withCookie(DeviceCredential::COOKIE_NAME, $credential)
        ->withHeader('Origin', config('app.url'))
        ->getJson('/api/pos/device')
        ->assertOk()
        ->assertJsonPath('data.id', $device->id)
        ->assertSessionHas('current_organization_id', $organization->id)
        ->assertSessionHas('current_store_id', $store->id);
});

it('invalidates the old browser credential when the device is paired again', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    $oldCredential = app(DeviceCredential::class)->issue($device);
    $newCredential = app(DeviceCredential::class)->issue($device);

    $this->actingAs($user)
        ->withCredentials()
        ->withCookie(DeviceCredential::COOKIE_NAME, $oldCredential)
        ->withHeader('Origin', config('app.url'))
        ->getJson('/api/pos/device')
        ->assertForbidden();

    $this->withCredentials()
        ->withCookie(DeviceCredential::COOKIE_NAME, $newCredential)
        ->withHeader('Origin', config('app.url'))
        ->getJson('/api/pos/device')
        ->assertOk();
});

it('lets an unpaired cashier open activation setup without printer management access', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $cashier = activationUser($organization, $store);

    $this->actingAs($cashier)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->get('/pos/device-setup')
        ->assertOk()
        ->assertSee('"can_manage_printers":false', false);
});
