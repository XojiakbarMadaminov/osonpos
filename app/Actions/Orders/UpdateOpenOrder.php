<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOpenOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(Order $order, ?string $note): Order
    {
        return DB::transaction(function () use ($order, $note): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureOpenCurrentOrder($order);
            $order->update(['note' => $note]);

            return $order;
        });
    }

    private function ensureOpenCurrentOrder(Order $order): void
    {
        if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
            || $order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages(['order' => 'Only a current-store open order can be changed.']);
        }
    }
}
