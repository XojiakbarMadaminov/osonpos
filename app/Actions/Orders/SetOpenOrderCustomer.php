<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetOpenOrderCustomer
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(Order $order, ?Customer $customer): Order
    {
        return DB::transaction(function () use ($order, $customer): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $organization = $this->tenantContext->requireCurrent();
            $store = $this->storeContext->requireCurrent();

            if ((int) $order->organization_id !== (int) $organization->getKey()
                || (int) $order->store_id !== (int) $store->getKey()
                || $order->status !== OrderStatus::Open) {
                throw ValidationException::withMessages([
                    'order' => 'Mijozni faqat joriy filialdagi ochiq buyurtmada o‘zgartirish mumkin.',
                ]);
            }

            if ($customer && (int) $customer->organization_id !== (int) $organization->getKey()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Mijoz joriy tashkilotga tegishli emas.',
                ]);
            }

            $order->forceFill([
                'customer_id' => $customer?->getKey(),
                'customer_name' => $customer?->name,
                'customer_phone' => $customer?->phone,
            ])->save();

            return $order;
        });
    }
}
