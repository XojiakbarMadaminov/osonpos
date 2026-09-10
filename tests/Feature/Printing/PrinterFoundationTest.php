<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Printing\PrinterRoutingService;
use App\Enums\OrganizationRole;
use App\Enums\PrintType;
use App\Models\Device;
use App\Models\Organization;
use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

function printerUser(Organization $organization, Store $store, OrganizationRole $role): User
{
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $user,
        $organization,
        fn (User $tenantUser) => $tenantUser->assignRole($role->value),
    );

    return $user;
}

it('routes both initial print types to one logical printer', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $printer = Printer::factory()->for($organization)->for($store)->create();
    foreach (PrintType::cases() as $printType) {
        PrintRoute::factory()->for($organization)->for($store)->for($printer)->create([
            'print_type' => $printType,
        ]);
    }

    $routing = app(PrinterRoutingService::class);

    expect($routing->resolve(PrintType::CustomerReceipt, $store)->is($printer))->toBeTrue()
        ->and($routing->resolve(PrintType::KitchenTicket, $store)->is($printer))->toBeTrue();
});

it('uses a changed route without physical printer names in routing logic', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $first = Printer::factory()->for($organization)->for($store)->create();
    $second = Printer::factory()->for($organization)->for($store)->create();
    $route = PrintRoute::factory()->for($organization)->for($store)->for($first)->create([
        'print_type' => PrintType::KitchenTicket,
    ]);
    $route->printer()->associate($second)->save();

    expect(app(PrinterRoutingService::class)->resolve(PrintType::KitchenTicket, $store)->is($second))->toBeTrue();
});

it('rejects printer routes that cross a store boundary', function () {
    $organization = Organization::factory()->create();
    $firstStore = Store::factory()->for($organization)->create();
    $secondStore = Store::factory()->for($organization)->create();
    $printer = Printer::factory()->for($organization)->for($firstStore)->create();

    expect(fn () => PrintRoute::factory()
        ->for($organization)
        ->for($secondStore)
        ->for($printer)
        ->create(['print_type' => PrintType::CustomerReceipt]))
        ->toThrow(ValidationException::class);
});

it('binds a logical printer to a physical name only for the current device and store', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $manager = printerUser($organization, $store, OrganizationRole::Manager);
    $device = Device::factory()->for($organization)->for($store)->create();
    $printer = Printer::factory()->for($organization)->for($store)->create();

    $this->actingAs($manager)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
            'current_device_id' => $device->id,
        ])
        ->putJson("/api/pos/printers/{$printer->id}/binding", [
            'system_name' => 'EPSON TM-T20III',
        ])
        ->assertOk()
        ->assertJsonPath('data.device_id', $device->id);

    expect($printer->refresh()->system_name)->toBe('EPSON TM-T20III')
        ->and($printer->device_id)->toBe($device->id);
});

it('denies printer configuration without printers manage permission', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $cashier = printerUser($organization, $store, OrganizationRole::Cashier);
    $device = Device::factory()->for($organization)->for($store)->create();
    $session = [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        'current_device_id' => $device->id,
    ];

    $this->actingAs($cashier)->withSession($session)->getJson('/api/pos/printers')->assertForbidden();
    $this->actingAs($cashier)->withSession($session)->get('/pos/device-setup')->assertForbidden();
});

it('limits printer admin records by tenant and assigned store', function () {
    $organization = Organization::factory()->create();
    $allowedStore = Store::factory()->for($organization)->create();
    $deniedStore = Store::factory()->for($organization)->create();
    $manager = printerUser($organization, $allowedStore, OrganizationRole::Manager);
    $deniedPrinter = Printer::factory()->for($organization)->for($deniedStore)->create();
    app(TenantContext::class)->resolveFor($manager, $organization->id);

    expect($manager->can('view', $deniedPrinter))->toBeFalse();
});
