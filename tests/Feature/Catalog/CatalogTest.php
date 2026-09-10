<?php

use App\Actions\Organizations\AssignOrganizationOwner;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Catalog\CatalogRepository;
use App\Enums\OrganizationRole;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('returns only active catalog records for one organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $activeCategory = Category::factory()->for($organization)->create(['name' => 'Meals']);
    $inactiveCategory = Category::factory()->for($organization)->create(['is_active' => false]);
    Product::factory()->for($organization)->for($activeCategory)->create(['name' => 'Burger']);
    Product::factory()->for($organization)->for($activeCategory)->create(['name' => 'Hidden', 'is_active' => false]);
    Product::factory()->for($organization)->for($inactiveCategory)->create(['name' => 'Inactive category product']);
    $otherCategory = Category::factory()->for($otherOrganization)->create();
    Product::factory()->for($otherOrganization)->for($otherCategory)->create(['name' => 'Foreign product']);

    $catalog = app(CatalogRepository::class)->forOrganization($organization);

    expect($catalog)->toHaveCount(1)
        ->and($catalog[0]['name'])->toBe('Meals')
        ->and(collect($catalog[0]['products'])->pluck('name')->all())->toBe(['Burger']);
});

it('invalidates the catalog cache after catalog changes', function () {
    $organization = Organization::factory()->create();
    $category = Category::factory()->for($organization)->create();
    $repository = app(CatalogRepository::class);

    expect($repository->forOrganization($organization)[0]['products'])->toBe([]);

    Product::factory()->for($organization)->for($category)->create(['name' => 'Lavash']);

    expect(collect($repository->forOrganization($organization)[0]['products'])->pluck('name')->all())
        ->toBe(['Lavash']);
});

it('stores product money as an integer and rejects negative values', function () {
    $organization = Organization::factory()->create();
    $category = Category::factory()->for($organization)->create();
    $product = Product::factory()->for($organization)->for($category)->create(['price' => 65000]);

    expect($product->price)->toBeInt()->toBe(65000)
        ->and(fn () => Product::factory()->for($organization)->for($category)->create(['price' => -1]))
        ->toThrow(QueryException::class);
});

it('rejects a category belonging to another tenant', function () {
    $organization = Organization::factory()->create();
    $otherCategory = Category::factory()->create();

    expect(fn () => Product::factory()->for($organization)->for($otherCategory)->create())
        ->toThrow(ValidationException::class);
});

it('allows a manager to manage catalog and denies a cashier', function () {
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

    $this->actingAs($manager)->withSession($session)->get('/admin/products')->assertOk();
    $this->actingAs($manager)->withSession($session)->get('/admin/categories')->assertOk();
    $this->actingAs($cashier)->withSession($session)->get('/admin/products')->assertForbidden();
    $this->actingAs($cashier)->withSession($session)->get('/admin/categories')->assertForbidden();
});

it('does not resolve another tenant product through a direct admin URL', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = User::factory()->create();
    $store->users()->attach($owner);
    app(AssignOrganizationOwner::class)->execute($organization, $owner);
    $otherCategory = Category::factory()->for($otherOrganization)->create();
    $otherProduct = Product::factory()->for($otherOrganization)->for($otherCategory)->create();

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->get("/admin/products/{$otherProduct->id}/edit")
        ->assertNotFound();
});

it('creates products at organization scope without a store column', function () {
    $organization = Organization::factory()->create();
    $category = Category::factory()->for($organization)->create();
    $product = Product::factory()->for($organization)->for($category)->create();

    expect($product->organization_id)->toBe($organization->id)
        ->and(array_key_exists('store_id', $product->getAttributes()))->toBeFalse();
});
