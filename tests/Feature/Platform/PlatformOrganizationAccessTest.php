<?php

use App\Domain\Authorization\AccessibleAdminStores;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Platform\PlatformOrganizationAccess;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

it('shows every organization in the platform management selector', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    Organization::factory()->create(['name' => 'Birinchi tashkilot']);
    Organization::factory()->create(['name' => 'Ikkinchi tashkilot']);

    $this->actingAs($platformAdmin)
        ->get('/platform')
        ->assertOk()
        ->assertSee('Tashkilot paneliga kirish')
        ->assertSee('Birinchi tashkilot')
        ->assertSee('Ikkinchi tashkilot')
        ->assertSee('Admin panelga kirish');
});

it('lets a platform administrator enter an organization without membership', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create(['name' => 'Tanlangan tashkilot']);
    $store = Store::factory()->for($organization)->create(['name' => 'Birinchi filial']);

    $this->actingAs($platformAdmin)
        ->post(route('platform.organization-access.enter'), ['organization_id' => $organization->id])
        ->assertRedirect('/admin')
        ->assertSessionHas(PlatformOrganizationAccess::SESSION_KEY, $organization->id)
        ->assertSessionHas('current_organization_id', $organization->id)
        ->assertSessionHas('current_store_id', $store->id);

    $this->get('/admin/stores/create')
        ->assertOk()
        ->assertSee('Platforma boshqaruv rejimi')
        ->assertSee('Tanlangan tashkilot')
        ->assertSee('Platformaga qaytish');
});

it('keeps platform management access inside the selected organization', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $selectedOrganization = Organization::factory()->create(['name' => 'Tanlangan tashkilot']);
    $foreignOrganization = Organization::factory()->create(['name' => 'Begona tashkilot']);
    $firstStore = Store::factory()->for($selectedOrganization)->create(['name' => 'Birinchi filial']);
    $secondStore = Store::factory()->for($selectedOrganization)->create(['name' => 'Ikkinchi filial']);
    Store::factory()->for($foreignOrganization)->create(['name' => 'Begona filial']);

    $session = [
        PlatformOrganizationAccess::SESSION_KEY => $selectedOrganization->id,
        'current_organization_id' => $foreignOrganization->id,
        'current_store_id' => $firstStore->id,
    ];

    $this->actingAs($platformAdmin)
        ->withSession($session)
        ->get('/admin/stores')
        ->assertOk()
        ->assertSee('Birinchi filial')
        ->assertSee('Ikkinchi filial')
        ->assertDontSee('Begona filial');

    $this->post(route('admin.context.switch'), ['store_id' => $secondStore->id])
        ->assertRedirect('/admin')
        ->assertSessionHas(PlatformOrganizationAccess::SESSION_KEY, $selectedOrganization->id)
        ->assertSessionHas('current_organization_id', $selectedOrganization->id)
        ->assertSessionHas('current_store_id', $secondStore->id);
});

it('grants full organization permissions only while platform management access is active', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();

    $this->actingAs($platformAdmin)->withSession([
        PlatformOrganizationAccess::SESSION_KEY => $organization->id,
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ]);

    app(TenantContext::class)->resolveFor($platformAdmin, $organization->id);

    expect(app(OrganizationAuthorization::class)->allows($platformAdmin, OrganizationPermission::UsersManage))->toBeTrue()
        ->and(app(AccessibleAdminStores::class)->forUser($platformAdmin)->modelKeys())->toContain($store->id);

    app(PlatformOrganizationAccess::class)->leave();
    app(TenantContext::class)->clear();

    expect(fn () => app(TenantContext::class)->resolveFor($platformAdmin, $organization->id))
        ->toThrow(AuthorizationException::class);
});

it('rejects organization management access for ordinary users and organizations without an active store', function () {
    $ordinaryUser = User::factory()->create();
    $organization = Organization::factory()->create();
    Store::factory()->for($organization)->create(['is_active' => false]);

    $this->actingAs($ordinaryUser)
        ->post(route('platform.organization-access.enter'), ['organization_id' => $organization->id])
        ->assertForbidden();

    $platformAdmin = User::factory()->platformAdmin()->create();

    $this->actingAs($platformAdmin)
        ->from('/platform')
        ->post(route('platform.organization-access.enter'), ['organization_id' => $organization->id])
        ->assertRedirect('/platform')
        ->assertSessionHasErrors(['organizationId' => 'Bu tashkilotda faol filial mavjud emas.']);
});

it('returns to the platform and clears the selected organization context', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();

    $this->actingAs($platformAdmin)
        ->withSession([
            PlatformOrganizationAccess::SESSION_KEY => $organization->id,
            'current_organization_id' => $organization->id,
            'current_store_id' => $store->id,
        ])
        ->post(route('platform.organization-access.leave'))
        ->assertRedirect('/platform')
        ->assertSessionMissing(PlatformOrganizationAccess::SESSION_KEY)
        ->assertSessionMissing('current_organization_id')
        ->assertSessionMissing('current_store_id');

    $this->get('/admin')->assertForbidden();
});
