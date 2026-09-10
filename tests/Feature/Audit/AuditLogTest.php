<?php

use App\Actions\Orders\CancelOrder;
use App\Actions\Organizations\AssignOrganizationOwner;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;

function auditOwner(Organization $organization, Store $store): User
{
    $owner = User::factory()->create();
    $store->users()->attach($owner);
    app(AssignOrganizationOwner::class)->execute($organization, $owner);

    return $owner;
}

it('records a price change with the correct actor and tenant', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = auditOwner($organization, $store);
    $product = Product::factory()->for($organization)->create(['price' => 10000]);
    $this->actingAs($owner);
    app(TenantContext::class)->resolveFor($owner, $organization->id);

    $product->update(['price' => 15000]);

    $audit = AuditLog::query()->where('event', AuditEvent::ProductPriceChanged)->sole();
    expect($audit->organization_id)->toBe($organization->id)
        ->and($audit->actor_id)->toBe($owner->id)
        ->and($audit->auditable_id)->toBe((string) $product->id)
        ->and($audit->old_values)->toBe(['price' => 10000])
        ->and($audit->new_values)->toBe(['price' => 15000]);
});

it('records an order cancellation in the same transaction', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = auditOwner($organization, $store);
    $order = Order::factory()->for($organization)->for($store)->for($owner, 'creator')->create();
    $tenantContext = app(TenantContext::class);
    $tenantContext->resolveFor($owner, $organization->id);
    app(StoreContext::class)->resolveFor($owner, $tenantContext, $store->id);

    app(CancelOrder::class)->execute($order, $owner);

    $audit = AuditLog::query()->where('event', AuditEvent::OrderCancelled)->sole();
    expect($audit->organization_id)->toBe($organization->id)
        ->and($audit->store_id)->toBe($store->id)
        ->and($audit->actor_id)->toBe($owner->id);
});

it('keeps organization audit listings tenant isolated', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['name' => 'Local Audit Store']);
    Store::factory()->for($otherOrganization)->create(['name' => 'Foreign Audit Store']);
    $owner = auditOwner($organization, $store);

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertSee('Local Audit Store')
        ->assertDontSee('Foreign Audit Store');
});

it('allows only owners to read organization audit logs', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $manager = User::factory()->create();
    $organization->users()->attach($manager);
    $store->users()->attach($manager);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $manager,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Manager->value),
    );

    $this->actingAs($manager)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/audit-logs')
        ->assertForbidden();
});

it('allows platform administrators to inspect audit logs across tenants', function () {
    Store::factory()->create();
    Store::factory()->create();
    $platformAdmin = User::factory()->platformAdmin()->create();

    $this->actingAs($platformAdmin)
        ->get('/platform/audit-logs')
        ->assertOk();
});
