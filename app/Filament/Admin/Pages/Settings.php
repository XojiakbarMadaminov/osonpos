<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Authorization\StoreAccess;
use App\Models\Store;
use App\Support\StoreContext;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

class Settings extends Page
{
    protected string $view = 'filament.admin.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public ?int $organizationId = null;

    public ?int $storeId = null;

    public function mount(): void
    {
        $this->organizationId = app(TenantContext::class)->id();
        $this->storeId = session('current_store_id');
    }

    public static function canAccess(): bool
    {
        $user = request()->user();
        $organization = app(TenantContext::class)->current();

        return $user && $organization && $user->organizations()->whereKey($organization)->exists();
    }

    public function organizations(): Collection
    {
        return request()->user()->organizations()->orderBy('name')->get();
    }

    public function stores(): Collection
    {
        if (! $this->organizationId) {
            return new Collection;
        }

        $tenant = app(TenantContext::class);
        $tenant->resolveFor(request()->user(), $this->organizationId);

        return Store::query()
            ->where('organization_id', $this->organizationId)
            ->whereIn('id', app(StoreAccess::class)->accessibleStoreIds(request()->user()))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function saveContext(TenantContext $tenants, StoreContext $stores): void
    {
        $validated = $this->validate([
            'organizationId' => [
                'required',
                Rule::exists('organization_user', 'organization_id')->where('user_id', request()->user()->getKey()),
            ],
            'storeId' => ['required', 'integer'],
        ]);

        $organization = $tenants->resolveFor(request()->user(), $validated['organizationId']);
        $store = $stores->resolveFor(request()->user(), $tenants, $validated['storeId']);

        session([
            'current_organization_id' => $organization->getKey(),
            'current_store_id' => $store->getKey(),
        ]);

        Notification::make()->success()->title('Organization and store updated')->send();
        $this->redirect(static::getUrl());
    }
}
