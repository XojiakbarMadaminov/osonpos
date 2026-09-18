<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Table;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveOpenDineInOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(Order $order, int $tableId): Order
    {
        return DB::transaction(function () use ($order, $tableId): Order {
            $organization = $this->tenantContext->requireCurrent();
            $store = $this->storeContext->requireCurrent();
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ((int) $order->organization_id !== (int) $organization->getKey()
                || (int) $order->store_id !== (int) $store->getKey()
                || $order->status !== OrderStatus::Open
                || $order->type !== OrderType::DineIn) {
                throw ValidationException::withMessages([
                    'order' => 'Faqat joriy filialdagi ochiq stol buyurtmasini ko‘chirish mumkin.',
                ]);
            }

            if ((int) $order->table_id === $tableId) {
                return $order;
            }

            $table = Table::query()
                ->whereKey($tableId)
                ->where('organization_id', $organization->getKey())
                ->where('store_id', $store->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $table) {
                throw ValidationException::withMessages([
                    'table_id' => 'Joriy filialdan faol stolni tanlang.',
                ]);
            }

            $isOccupied = Order::query()
                ->where('organization_id', $organization->getKey())
                ->where('store_id', $store->getKey())
                ->where('table_id', $table->getKey())
                ->where('status', OrderStatus::Open)
                ->exists();

            if ($isOccupied) {
                throw ValidationException::withMessages([
                    'table_id' => 'Tanlangan stol band. Boshqa bo‘sh stolni tanlang.',
                ]);
            }

            $order->update(['table_id' => $table->getKey()]);

            return $order;
        });
    }
}
