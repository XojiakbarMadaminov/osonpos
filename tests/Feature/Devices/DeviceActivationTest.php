<?php

use App\Actions\Devices\SetDeviceActivationCode;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Filament\Admin\Resources\Devices\Pages\CreateDevice as CreateDevicePage;
use App\Filament\Admin\Resources\Devices\Pages\ListDevices;
use App\Models\Device;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\DeviceActivationCode;
use App\Support\DeviceCredential;
use App\Support\StoreContext;
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
    $tenantContext = app(TenantContext::class);
    $tenantContext->resolveFor($owner, $organization->id);
    app(StoreContext::class)->resolveFor($owner, $tenantContext, $store->id);

    $this->actingAs($owner)->withSession([
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateDevicePage::class)
        ->assertFormFieldDoesNotExist('store_id')
        ->assertFormFieldDoesNotExist('code')
        ->fillForm([
            'name' => 'Asosiy kassa',
            'activation_code' => '482731',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $device = Device::query()->sole();
    expect($device->organization_id)->toBe($organization->id)
        ->and($device->store_id)->toBe($store->id)
        ->and($device->code)->toStartWith('POS-')
        ->and($device->activation_code_hash)->toBe(app(DeviceActivationCode::class)->hash('482731'))
        ->and($device->getAttributes())->not->toContain('482731');

    Livewire::test(ListDevices::class)
        ->callTableAction('activation_code', $device, [
            'activation_code' => '593842',
        ])
        ->assertHasNoActionErrors();

    expect($device->refresh()->activation_code_hash)
        ->toBe(app(DeviceActivationCode::class)->hash('593842'));
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

it('activates a device with its permanent code and stores a long lived browser credential', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    $code = '482731';
    app(SetDeviceActivationCode::class)->execute($device, $code);

    $response = $this->actingAs($user)->postJson('/api/pos/devices/activate', [
        'activation_code' => mb_strtolower($code),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $device->id)
        ->assertCookie(DeviceCredential::COOKIE_NAME)
        ->assertSessionHas('current_organization_id', $organization->id)
        ->assertSessionHas('current_store_id', $store->id)
        ->assertSessionHas(DeviceCredential::SESSION_KEY);

    expect($device->refresh()->activation_code_hash)->toBe(app(DeviceActivationCode::class)->hash($code))
        ->and($device->credential_hash)->not->toBeNull()
        ->and($device->activated_at)->not->toBeNull();

    $cookie = app(DeviceCredential::class)->cookie('device.secret');
    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addMonths(11)->timestamp);
});

it('keeps a permanent activation code reusable and rejects an invalid code', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    $code = '482731';
    app(SetDeviceActivationCode::class)->execute($device, $code);

    $this->actingAs($user)->postJson('/api/pos/devices/activate', [
        'activation_code' => '999999',
    ])->assertUnprocessable()->assertJsonValidationErrors('activation_code');

    $this->postJson('/api/pos/devices/activate', ['activation_code' => $code])->assertOk();
    $this->postJson('/api/pos/devices/activate', ['activation_code' => $code])
        ->assertOk();

    expect($device->refresh()->activation_code_hash)->not->toBeNull();
});

it('requires an exactly 6 digit activation code', function (string $code) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/pos/devices/activate', [
        'activation_code' => $code,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('activation_code')
        ->assertJsonPath('errors.activation_code.0', 'Aktivatsiya kodi 6 xonali raqam bo‘lishi kerak.');
})->with(['12345', 'ABC123', '1234567']);

it('invalidates the previous permanent code when an admin replaces it', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();

    app(SetDeviceActivationCode::class)->execute($device, '482731');
    app(SetDeviceActivationCode::class)->execute($device, '593842');

    $this->actingAs($user)->postJson('/api/pos/devices/activate', [
        'activation_code' => '482731',
    ])->assertUnprocessable()->assertJsonValidationErrors('activation_code');

    $this->postJson('/api/pos/devices/activate', [
        'activation_code' => '593842',
    ])->assertOk()->assertJsonPath('data.id', $device->id);
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
    $code = '482731';
    app(SetDeviceActivationCode::class)->execute($device, $code);

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

it('logs a cashier out of pos and lets the next cashier reuse the registered device', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $firstCashier = activationUser($organization, $store);
    $secondCashier = activationUser($organization, $store);
    enablePosForActivation($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    $credential = app(DeviceCredential::class)->issue($device);
    $session = [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ];

    $this->actingAs($firstCashier)
        ->withSession($session)
        ->withCookie(DeviceCredential::COOKIE_NAME, $credential)
        ->post(route('pos.logout'))
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHas('url.intended', url('/pos'))
        ->assertSessionHas('pos_logout_success');

    $this->assertGuest();

    $this->actingAs($secondCashier)
        ->withCookie(DeviceCredential::COOKIE_NAME, $credential)
        ->get('/pos')
        ->assertOk()
        ->assertSessionHas('current_organization_id', $organization->id)
        ->assertSessionHas('current_store_id', $store->id);
});

it('requires the cashier to close the current device shift before pos logout', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $cashier = activationUser($organization, $store);
    $device = Device::factory()->for($organization)->for($store)->create();
    $credential = app(DeviceCredential::class)->issue($device);
    Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create();

    $this->actingAs($cashier)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->withCookie(DeviceCredential::COOKIE_NAME, $credential)
        ->post(route('pos.logout'))
        ->assertRedirect('/pos')
        ->assertSessionHas('pos_logout_error', 'Akkauntdan chiqishdan oldin joriy smenani yoping.');

    $this->assertAuthenticatedAs($cashier);
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
