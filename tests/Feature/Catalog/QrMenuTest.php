<?php

use App\Actions\Organizations\AssignOrganizationOwner;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Catalog\QrMenuCode;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Filament\Admin\Pages\QrMenu;
use App\Filament\Admin\Resources\Products\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function enableQrFeature(Organization $organization): Subscription
{
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($organization)->for($plan)->create();
    $subscription->features()->attach(Feature::query()->where('code', 'qr_menu')->sole());

    return $subscription;
}

it('shows only the active catalog of the scanned store without cost', function () {
    Storage::fake('public');
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['name' => 'Markaziy filial', 'is_qr_menu_enabled' => true]);
    $otherStore = Store::factory()->for($organization)->create(['is_qr_menu_enabled' => true]);
    $otherOrganization = Organization::factory()->create();
    enableQrFeature($organization);

    $category = Category::factory()->for($organization)->for($store)->create(['name' => 'Taomlar']);
    $product = Product::factory()->for($organization)->for($category)->create([
        'name' => 'Osh', 'description' => 'Mazali osh', 'image_path' => 'menu-products/osh.jpg',
        'price' => 65000, 'cost_price' => 42123,
    ]);
    Product::factory()->for($organization)->for($category)->create(['name' => 'Yashirin mahsulot', 'is_active' => false]);
    $inactiveCategory = Category::factory()->for($organization)->for($store)->create(['name' => 'Yashirin kategoriya', 'is_active' => false]);
    Product::factory()->for($organization)->for($inactiveCategory)->create(['name' => 'Yashirin taom']);
    $otherCategory = Category::factory()->for($organization)->for($otherStore)->create();
    Product::factory()->for($organization)->for($otherCategory)->create(['name' => 'Boshqa filial taomi']);
    $foreignCategory = Category::factory()->for($otherOrganization)->create();
    Product::factory()->for($otherOrganization)->for($foreignCategory)->create(['name' => 'Begona taom']);

    $url = route('menu.show', ['token' => $store->menu_token]);
    $this->get($url)->assertOk()
        ->assertSeeText('Markaziy filial')
        ->assertSeeText('Taomlar')
        ->assertSeeText('Osh')
        ->assertSeeText('Mazali osh')
        ->assertSeeText('65 000 UZS')
        ->assertSee('menu-products/osh.jpg')
        ->assertDontSeeText('42123')
        ->assertDontSeeText('Tannarx')
        ->assertDontSeeText('Yashirin mahsulot')
        ->assertDontSeeText('Yashirin kategoriya')
        ->assertDontSeeText('Boshqa filial taomi')
        ->assertDontSeeText('Begona taom');

    $product->update(['price' => 70000]);
    $this->get($url)->assertSeeText('70 000 UZS')->assertDontSeeText('65 000 UZS');
});

it('closes the public menu when any publishing condition fails', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['is_qr_menu_enabled' => true]);
    $subscription = enableQrFeature($organization);
    $url = route('menu.show', ['token' => $store->menu_token]);

    $this->get($url)->assertOk();
    $store->update(['is_qr_menu_enabled' => false]);
    $this->get($url)->assertNotFound();
    $store->update(['is_qr_menu_enabled' => true, 'is_active' => false]);
    $this->get($url)->assertNotFound();
    $store->update(['is_active' => true]);
    $organization->update(['status' => OrganizationStatus::Suspended]);
    $this->get($url)->assertNotFound();
    $organization->update(['status' => OrganizationStatus::Active]);
    $subscription->features()->detach();
    $this->get($url)->assertNotFound();
    $subscription->features()->attach(Feature::query()->where('code', 'qr_menu')->sole());
    $subscription->update(['ends_at' => now()->subMinute()]);
    $this->get($url)->assertNotFound();
    $this->get('/menu/invalid-token')->assertNotFound();
});

it('assigns distinct stable QR links to stores', function () {
    $organization = Organization::factory()->create();
    $first = Store::factory()->for($organization)->create();
    $second = Store::factory()->for($organization)->create();
    $token = $first->menu_token;

    $first->update(['name' => 'Yangi nom']);

    expect($token)->toHaveLength(26)
        ->and($first->fresh()->menu_token)->toBe($token)
        ->and($second->menu_token)->not->toBe($token);
});

it('lets an authorized owner publish the active store and download a usable SVG', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $otherStore = Store::factory()->for($organization)->create();
    $owner = User::factory()->create();
    app(AssignOrganizationOwner::class)->execute($organization, $owner);
    enableQrFeature($organization);
    $session = ['current_organization_id' => $organization->id, 'current_store_id' => $store->id];

    $this->actingAs($owner)->withSession($session);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/qr-menu')->assertOk()->assertSeeText('QR kodni yuklab olish');
    Livewire::test(QrMenu::class)
        ->set('enabled', true)
        ->call('save')
        ->assertHasNoErrors();
    Livewire::test(QrMenu::class)
        ->call('downloadQrCode')
        ->assertFileDownloaded('filial-qr-menyu.svg');

    expect($store->fresh()->is_qr_menu_enabled)->toBeTrue()
        ->and($store->fresh()->menu_token)->toBe($store->menu_token)
        ->and($otherStore->fresh()->is_qr_menu_enabled)->toBeFalse();

    $svg = app(QrMenuCode::class)->svg(route('menu.show', ['token' => $store->menu_token]));
    expect($svg)->toContain('<svg');
});

it('blocks QR settings without the feature or permission', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = User::factory()->create();
    app(AssignOrganizationOwner::class)->execute($organization, $owner);
    $this->actingAs($owner)->withSession(['current_organization_id' => $organization->id, 'current_store_id' => $store->id]);

    $this->get('/admin/qr-menu')->assertForbidden();

    enableQrFeature($organization);
    $this->get('/admin/qr-menu')->assertOk();

    $cashier = User::factory()->create();
    $organization->users()->attach($cashier);
    $store->users()->attach($cashier);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $cashier,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Cashier->value),
    );
    $this->actingAs($cashier)->get('/admin/qr-menu')->assertForbidden();
});

it('accepts a product photo and rejects a non-image upload', function () {
    Storage::fake('public');
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $category = Category::factory()->for($organization)->for($store)->create();
    $owner = User::factory()->create();
    app(AssignOrganizationOwner::class)->execute($organization, $owner);
    $this->actingAs($owner)->withSession(['current_organization_id' => $organization->id, 'current_store_id' => $store->id]);
    $tenantContext = app(TenantContext::class);
    $tenantContext->resolveFor($owner, $organization->id);
    app(StoreContext::class)->resolveFor($owner, $tenantContext, $store->id);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateProduct::class)
        ->assertSee('Yuklashda xatolik yuz berdi')
        ->fillForm([
            'category_id' => $category->id,
            'name' => 'Manti',
            'description' => 'Qo‘lda tayyorlangan',
            'image_path' => UploadedFile::fake()->image('manti.jpg')->size(2560),
            'price' => 35000,
            'cost_price' => 15000,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('name', 'Manti')->sole();
    expect($product->description)->toBe('Qo‘lda tayyorlangan')
        ->and($product->image_path)->toStartWith('menu-products/');
    Storage::disk('public')->assertExists($product->image_path);
    $oldPath = $product->image_path;
    Storage::disk('public')->put('menu-products/new.jpg', 'new image');
    $product->update(['image_path' => 'menu-products/new.jpg']);
    Storage::disk('public')->assertMissing($oldPath);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'category_id' => $category->id,
            'name' => 'Noto‘g‘ri rasm',
            'image_path' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
            'price' => 35000,
            'cost_price' => 15000,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['image_path']);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'category_id' => $category->id,
            'name' => 'Katta rasm',
            'image_path' => UploadedFile::fake()->image('large.jpg')->size(2561),
            'price' => 35000,
            'cost_price' => 15000,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['image_path']);
});
