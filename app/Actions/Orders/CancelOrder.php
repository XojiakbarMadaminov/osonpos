<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(Order $order, User $user): Order
    {
        return DB::transaction(function () use ($order, $user): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
                || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
                || $order->status !== OrderStatus::Open) {
                throw ValidationException::withMessages(['order' => 'Faqat joriy filialdagi ochiq buyurtmani bekor qilish mumkin.']);
            }

            $order->closer()->associate($user);
            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'closed_at' => now(),
            ])->save();

            return $order;
        });
    }
}
