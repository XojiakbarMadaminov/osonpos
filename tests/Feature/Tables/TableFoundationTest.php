<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Store\TableOccupancy;
use App\Enums\OrderType;
use App\Enums\OrganizationRole;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('keeps tables store and tenant scoped', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $table = Table::factory()->for($organization)->for($store)->create();

    expect($table->organization_id)->toBe($organization->id)
        ->and($table->store_id)->toBe($store->id)
        ->and(array_key_exists('occupied', $table->getAttributes()))->toBeFalse();
});

it('rejects a store from another organization', function () {
    $organization = Organization::factory()->create();
    $otherStore = Store::factory()->create();

    expect(fn () => Table::factory()->for($organization)->for($otherStore)->create())
        ->toThrow(ValidationException::class);
});

it('derives occupancy from an open order instead of persisted state', function () {
    $createdOrdersTable = ! Schema::hasTable('orders');

    if ($createdOrdersTable) {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('store_id');
            $table->foreignId('table_id')->nullable();
            $table->string('status');
        });
    }

    try {
        $organization = Organization::factory()->create();
        $store = Store::factory()->for($organization)->create();
        $table = Table::factory()->for($organization)->for($store)->create();
        $otherTable = Table::factory()->for($organization)->for($store)->create();
        Order::factory()->for($organization)->for($store)->for($table)->create([
            'type' => OrderType::DineIn,
        ]);

        expect(app(TableOccupancy::class)->isOccupied($table))->toBeTrue()
            ->and(app(TableOccupancy::class)->isOccupied($otherTable))->toBeFalse();
    } finally {
        if ($createdOrdersTable) {
            Schema::drop('orders');
        }
    }
});

it('limits admin table records to stores assigned to a manager', function () {
    $organization = Organization::factory()->create();
    $allowedStore = Store::factory()->for($organization)->create();
    $deniedStore = Store::factory()->for($organization)->create();
    $deniedTable = Table::factory()->for($organization)->for($deniedStore)->create();
    $manager = User::factory()->create();
    $organization->users()->attach($manager);
    $allowedStore->users()->attach($manager);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $manager,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Manager->value),
    );

    $this->actingAs($manager)
        ->withSession(['current_organization_id' => $organization->id])
        ->get("/admin/tables/{$deniedTable->id}/edit")
        ->assertNotFound();
});

it('allows a manager to configure tables and denies a cashier management', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $manager = User::factory()->create();
    $cashier = User::factory()->create();
    $organization->users()->attach([$manager->id, $cashier->id]);
    $store->users()->attach([$manager->id, $cashier->id]);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    $authorization = app(OrganizationAuthorization::class);
    $authorization->runForUserInTenant($manager, $organization, fn (User $user) => $user->assignRole(OrganizationRole::Manager->value));
    $authorization->runForUserInTenant($cashier, $organization, fn (User $user) => $user->assignRole(OrganizationRole::Cashier->value));
    $session = ['current_organization_id' => $organization->id];

    $this->actingAs($manager)->withSession($session)->get('/admin/tables/create')->assertOk();
    $this->actingAs($cashier)->withSession($session)->get('/admin/tables')->assertOk();
    $this->actingAs($cashier)->withSession($session)->get('/admin/tables/create')->assertForbidden();
});
