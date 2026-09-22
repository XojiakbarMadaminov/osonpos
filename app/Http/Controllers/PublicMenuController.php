<?php

namespace App\Http\Controllers;

use App\Domain\Subscription\SubscriptionAccess;
use App\Enums\OrganizationStatus;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Contracts\View\View;

class PublicMenuController
{
    public function __invoke(string $token, SubscriptionAccess $subscriptions): View
    {
        $store = Store::query()
            ->with('organization')
            ->where('menu_token', $token)
            ->where('is_active', true)
            ->where('is_qr_menu_enabled', true)
            ->firstOrFail();

        abort_unless(
            $store->organization->status === OrganizationStatus::Active
                && $subscriptions->hasFeature($store->organization, 'qr_menu'),
            404,
        );

        $categories = Category::query()
            ->select(['id', 'organization_id', 'store_id', 'name', 'sort_order'])
            ->forTenant($store->organization_id)
            ->forStore($store)
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query
                ->where('organization_id', $store->organization_id)
                ->where('store_id', $store->id)
                ->where('is_active', true))
            ->with(['products' => fn ($query) => $query
                ->select(['id', 'category_id', 'name', 'description', 'image_path', 'price', 'sort_order'])
                ->where('organization_id', $store->organization_id)
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('menu.show', compact('store', 'categories'));
    }
}
