<?php

use App\Actions\Organizations\AssignOrganizationOwner;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Actions\Organizations\SaveOrganizationUser;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\AdminNavigationGroup;
use App\Enums\OrganizationRole;
use App\Filament\Admin\Pages\Reports;
use App\Filament\Admin\Resources\Categories\CategoryResource;
use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Resources\Devices\DeviceResource;
use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Resources\Printers\PrinterResource;
use App\Filament\Admin\Resources\PrintRoutes\PrintRouteResource;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Filament\Admin\Resources\Shifts\ShiftResource;
use App\Filament\Admin\Resources\Stores\StoreResource;
use App\Filament\Admin\Resources\Tables\TableResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Http\Middleware\InitializeTenantContext;
use App\Models\Feature;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemRemoval;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

function adminOwner(Organization $organization, Store $store): User
{
    $owner = User::factory()->create();
    $store->users()->attach($owner);
    app(AssignOrganizationOwner::class)->execute($organization, $owner);

    return $owner;
}

it('persists tenant authorization context across filament livewire actions', function () {
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(InitializeTenantContext::class);
});

it('groups admin navigation by business function in the configured order', function () {
    $groups = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(fn ($group): ?string => $group->getLabel())
        ->values()
        ->all();

    expect($groups)->toBe([
        'Savdo',
        'Katalog',
        'Filial boshqaruvi',
        'Xodimlar va ruxsatlar',
    ]);

    $assignments = [
        Reports::class => AdminNavigationGroup::Sales,
        OrderResource::class => AdminNavigationGroup::Sales,
        CustomerResource::class => AdminNavigationGroup::Sales,
        ExpenseResource::class => AdminNavigationGroup::Sales,
        ShiftResource::class => AdminNavigationGroup::Sales,
        CategoryResource::class => AdminNavigationGroup::Catalog,
        ProductResource::class => AdminNavigationGroup::Catalog,
        StoreResource::class => AdminNavigationGroup::BranchManagement,
        TableResource::class => AdminNavigationGroup::BranchManagement,
        DeviceResource::class => AdminNavigationGroup::BranchManagement,
        PrinterResource::class => AdminNavigationGroup::BranchManagement,
        PrintRouteResource::class => AdminNavigationGroup::BranchManagement,
        UserResource::class => AdminNavigationGroup::StaffAndPermissions,
    ];

    foreach ($assignments as $resource => $group) {
        expect($resource::getNavigationGroup())->toBe($group);
    }

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    expect(RoleResource::getNavigationGroup())
        ->toBe(AdminNavigationGroup::StaffAndPermissions);
});

it('redirects the removed admin dashboard to the first authorized section', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $store);

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin')
        ->assertRedirect('/admin/reports');
});

it('keeps store order and user listings inside the current organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['name' => 'Current Branch']);
    $otherStore = Store::factory()->for($otherOrganization)->create(['name' => 'Foreign Branch']);
    $owner = adminOwner($organization, $store);
    $otherUser = User::factory()->create(['name' => 'Foreign Person']);
    $otherOrganization->users()->attach($otherUser);

    Order::factory()->for($organization)->for($store)->create([
        'display_number' => '#LOCAL-18',
        'created_by' => $owner,
    ]);
    Order::factory()->for($otherOrganization)->for($otherStore)->create([
        'display_number' => '#FOREIGN-18',
        'created_by' => $otherUser,
    ]);

    $session = ['current_organization_id' => $organization->id];

    $this->actingAs($owner)->withSession($session)->get('/admin/stores')
        ->assertOk()->assertSee('Current Branch')->assertDontSee('Foreign Branch');
    $this->actingAs($owner)->withSession($session)->get('/admin/orders')
        ->assertOk()->assertSee('#LOCAL-18')->assertDontSee('#FOREIGN-18');
    $this->actingAs($owner)->withSession($session)->get('/admin/users')
        ->assertOk()->assertSee($owner->name)->assertDontSee('Foreign Person');
});

it('shows original removed and remaining product quantities in order details', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $store);
    $order = Order::factory()->for($organization)->for($store)->for($owner, 'creator')->create([
        'subtotal' => 20000,
        'total' => 20000,
    ]);
    $item = OrderItem::factory()->for($organization)->for($store)->for($order)->for($owner, 'creator')->create([
        'product_name' => 'Sinov lavash',
        'quantity' => 3,
        'unit_price' => 10000,
        'total' => 30000,
    ]);
    OrderItemRemoval::factory()->for($organization)->for($store)->for($order)->for($item, 'orderItem')->for($owner, 'creator')->create([
        'quantity' => 1,
        'unit_price' => 10000,
        'total' => 10000,
    ]);

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id, 'current_store_id' => $store->id])
        ->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Sinov lavash')
        ->assertSee('Dastlabki miqdor')
        ->assertSee('Ayirilgan')
        ->assertSee('Qolgan miqdor');
});

it('limits store-owned admin listings to stores assigned to a manager', function () {
    $organization = Organization::factory()->create();
    $assigned = Store::factory()->for($organization)->create(['name' => 'Assigned Branch']);
    $unassigned = Store::factory()->for($organization)->create(['name' => 'Hidden Branch']);
    $manager = User::factory()->create();
    $organization->users()->attach($manager);
    $assigned->users()->attach($manager);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $manager,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Manager->value),
    );

    $this->actingAs($manager)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/stores')
        ->assertOk()
        ->assertSee('Assigned Branch')
        ->assertDontSee('Hidden Branch');

    $this->actingAs($manager)
        ->withSession(['current_organization_id' => $organization->id])
        ->get("/admin/stores/{$unassigned->id}/edit")
        ->assertNotFound();
});

it('rejects user assignment to a store outside the current tenant', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $foreignStore = Store::factory()->create();
    $owner = adminOwner($organization, $store);
    app(TenantContext::class)->resolveFor($owner, $organization->id);
    $role = $organization->roles()->where('name', OrganizationRole::Cashier->value)->sole();

    expect(fn () => app(SaveOrganizationUser::class)->execute(
        $owner,
        null,
        ['name' => 'New User', 'email' => 'new-user@example.test', 'password' => 'password'],
        $role->id,
        [$foreignStore->id],
    ))->toThrow(ValidationException::class);

    expect(User::query()->where('email', 'new-user@example.test')->exists())->toBeFalse();
});

it('assigns a new organization user to an allowed role and stores', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $store);
    $plan = Plan::factory()->create(['max_users' => 5]);
    Subscription::factory()->for($organization)->for($plan)->create();
    app(TenantContext::class)->resolveFor($owner, $organization->id);
    $role = $organization->roles()->where('name', OrganizationRole::Cashier->value)->sole();

    $member = app(SaveOrganizationUser::class)->execute(
        $owner,
        null,
        ['name' => 'Cashier One', 'email' => 'cashier-one@example.test', 'password' => 'password'],
        $role->id,
        [$store->id],
    );

    expect($member->organizations()->whereKey($organization)->exists())->toBeTrue()
        ->and($member->stores()->whereKey($store)->exists())->toBeTrue()
        ->and(app(OrganizationAuthorization::class)->runForUserInTenant(
            $member,
            $organization,
            fn (User $user): bool => $user->hasRole(OrganizationRole::Cashier->value),
        ))->toBeTrue();
});

it('lets an owner switch to any active store in the organization', function () {
    $organization = Organization::factory()->create();
    $firstStore = Store::factory()->for($organization)->create();
    $secondStore = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $firstStore);
    $tenantContext = app(TenantContext::class);
    $tenantContext->resolveFor($owner, $organization->id);

    $resolved = app(StoreContext::class)->resolveFor($owner, $tenantContext, $secondStore->id);

    expect($resolved->is($secondStore))->toBeTrue();
});

it('shows the active store switcher below the admin user profile', function () {
    $organization = Organization::factory()->create(['name' => 'Sinov tashkiloti']);
    $store = Store::factory()->for($organization)->create(['name' => 'Chilonzor filiali']);
    $owner = adminOwner($organization, $store);

    $this->actingAs($owner)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->get('/admin/reports')
        ->assertOk()
        ->assertSee('admin-active-store', false)
        ->assertSee('onchange="this.form.submit()"', false)
        ->assertDontSee('this.disabled', false)
        ->assertSeeText('Faol filial')
        ->assertSeeText('Sinov tashkiloti — Chilonzor filiali')
        ->assertDontSeeText('Sozlamalar')
        ->assertDontSeeText('Asosiy panel');
});

it('switches organization and store context from the admin profile menu', function () {
    $firstOrganization = Organization::factory()->create();
    $firstStore = Store::factory()->for($firstOrganization)->create();
    $owner = adminOwner($firstOrganization, $firstStore);

    $secondOrganization = Organization::factory()->create();
    $secondStore = Store::factory()->for($secondOrganization)->create();
    app(AssignOrganizationOwner::class)->execute($secondOrganization, $owner);

    $this->actingAs($owner)
        ->withSession([
            'current_organization_id' => $firstOrganization->id,
            'current_store_id' => $firstStore->id,
        ])
        ->post(route('admin.context.switch'), ['store_id' => $secondStore->id])
        ->assertRedirect('/admin')
        ->assertSessionHas('current_organization_id', $secondOrganization->id)
        ->assertSessionHas('current_store_id', $secondStore->id);
});

it('blocks inaccessible and inactive stores in the admin context switcher', function (string $storeKind) {
    $organization = Organization::factory()->create();
    $assignedStore = Store::factory()->for($organization)->create();
    $manager = User::factory()->create();
    $organization->users()->attach($manager);
    $assignedStore->users()->attach($manager);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $manager,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Manager->value),
    );

    $targetStore = match ($storeKind) {
        'unassigned' => Store::factory()->for($organization)->create(),
        'inactive' => Store::factory()->for($organization)->create(['is_active' => false]),
        'foreign' => Store::factory()->create(),
    };

    $this->actingAs($manager)
        ->withSession([
            'current_organization_id' => $organization->id,
            'current_store_id' => $assignedStore->id,
        ])
        ->from('/admin')
        ->post(route('admin.context.switch'), ['store_id' => $targetStore->id])
        ->assertRedirect('/admin')
        ->assertSessionHasErrors('store_id')
        ->assertSessionHas('current_organization_id', $organization->id)
        ->assertSessionHas('current_store_id', $assignedStore->id);
})->with(['unassigned', 'inactive', 'foreign']);

it('removes the separate admin settings page', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $store);

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/settings')
        ->assertNotFound();
});

it('hides table navigation when the subscription does not include tables', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = adminOwner($organization, $store);
    $plan = Plan::factory()->create();
    Subscription::factory()->for($organization)->for($plan)->create();
    $this->actingAs($owner);
    app(TenantContext::class)->resolveFor($owner, $organization->id);

    expect(TableResource::shouldRegisterNavigation())->toBeFalse();

    $tables = Feature::factory()->create(['code' => 'tables']);
    $plan->features()->attach($tables);

    expect(TableResource::shouldRegisterNavigation())->toBeTrue();
});

it('keeps the report page behind reports permission', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $cashier = User::factory()->create();
    $organization->users()->attach($cashier);
    $store->users()->attach($cashier);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $cashier,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Cashier->value),
    );

    $this->actingAs($cashier)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/reports')
        ->assertForbidden();
});
