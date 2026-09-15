<?php

namespace App\Actions\Printing;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItemRemoval;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkKitchenRemovalsPrinted
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(Order $order, array $removalIds): int
    {
        return DB::transaction(function () use ($order, $removalIds): int {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
                || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
                || ! in_array($order->status, [OrderStatus::Open, OrderStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['order' => 'Bu ayirish chekini joriy muhitda tasdiqlab bo‘lmaydi.']);
            }

            $removals = OrderItemRemoval::query()
                ->where('order_id', $order->getKey())
                ->where('kitchen_print_required', true)
                ->whereIn('id', $removalIds)
                ->lockForUpdate()
                ->get();

            if ($removals->count() !== count(array_unique($removalIds))) {
                throw ValidationException::withMessages([
                    'removal_ids' => 'Barcha ayirishlar shu buyurtmaga tegishli va oshxona cheki talab qiladigan bo‘lishi kerak.',
                ]);
            }

            return OrderItemRemoval::query()
                ->whereIn('id', $removals->pluck('id'))
                ->whereNull('kitchen_printed_at')
                ->update(['kitchen_printed_at' => now(), 'updated_at' => now()]);
        });
    }
}
