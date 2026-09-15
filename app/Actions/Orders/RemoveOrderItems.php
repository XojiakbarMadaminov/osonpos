<?php

namespace App\Actions\Orders;

use App\Actions\Payments\RecalculatePaymentStatus;
use App\Domain\Shift\CurrentShift;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemRemoval;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveOrderItems
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly CurrentShift $currentShift,
        private readonly RecalculateOrderTotals $recalculateTotals,
        private readonly RecalculatePaymentStatus $recalculatePaymentStatus,
    ) {}

    public function execute(Order $order, User $user, array $lines): Order
    {
        return DB::transaction(function () use ($order, $user, $lines): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureOpenCurrentOrder($order);

            if (! $this->currentShift->for($user)) {
                throw ValidationException::withMessages([
                    'shift' => 'Buyurtmadan mahsulot ayirish uchun avval smenani oching.',
                ]);
            }

            $itemIds = collect($lines)->pluck('order_item_id')->unique()->all();
            $items = OrderItem::query()
                ->where('order_id', $order->getKey())
                ->whereIn('id', $itemIds)
                ->withSum('removals', 'quantity')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($itemIds)) {
                throw ValidationException::withMessages([
                    'items' => 'Ayirilayotgan barcha mahsulotlar shu buyurtmaga tegishli bo‘lishi kerak.',
                ]);
            }

            $newRemovedTotal = 0;

            foreach ($lines as $index => $line) {
                $item = $items->get($line['order_item_id']);
                $existing = OrderItemRemoval::query()->whereKey($line['id'])->first();

                if ($existing) {
                    $sameOperation = $existing->order_id === $order->getKey()
                        && $existing->order_item_id === $item->getKey()
                        && $existing->quantity === (int) $line['quantity'];

                    if (! $sameOperation) {
                        throw ValidationException::withMessages([
                            "items.{$index}.id" => 'Ayirish identifikatori boshqa amal uchun ishlatilgan.',
                        ]);
                    }

                    continue;
                }

                $alreadyRemoved = $item->removedQuantity();
                $quantity = (int) $line['quantity'];

                if ($quantity > $item->quantity - $alreadyRemoved) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Ayirish miqdori mahsulotning qolgan miqdoridan oshmasligi kerak.',
                    ]);
                }

                $removal = new OrderItemRemoval;
                $removal->setAttribute('id', $line['id']);
                $removal->organization()->associate($order->organization_id);
                $removal->store()->associate($order->store_id);
                $removal->order()->associate($order);
                $removal->orderItem()->associate($item);
                $removal->creator()->associate($user);
                $removal->forceFill([
                    'quantity' => $quantity,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'total' => $item->unit_price * $quantity,
                    'kitchen_print_required' => $item->kitchen_printed_at !== null,
                ])->save();

                $item->setAttribute('removals_sum_quantity', $alreadyRemoved + $quantity);
                $newRemovedTotal += $removal->total;
            }

            $paidAmount = (int) $order->payments()->sum('amount');
            if ($order->total - $newRemovedTotal < $paidAmount) {
                throw ValidationException::withMessages([
                    'items' => 'Mahsulot ayirilganda buyurtma jami to‘langan summadan kamayib ketadi.',
                ]);
            }

            $order = $this->recalculateTotals->execute($order);
            $this->recalculatePaymentStatus->execute($order);

            $remainingQuantity = (int) $order->items()->sum('quantity')
                - (int) $order->itemRemovals()->sum('quantity');

            if ($remainingQuantity === 0 && $paidAmount > 0) {
                throw ValidationException::withMessages([
                    'items' => 'To‘lov mavjud buyurtmadan barcha mahsulotlarni ayirib bo‘lmaydi.',
                ]);
            }

            if ($remainingQuantity === 0) {
                $order->closer()->associate($user);
                $order->forceFill([
                    'status' => OrderStatus::Cancelled,
                    'closed_at' => now(),
                ])->save();
            }

            return $order;
        });
    }

    private function ensureOpenCurrentOrder(Order $order): void
    {
        $valid = (int) $order->organization_id === (int) $this->tenantContext->requireCurrent()->getKey()
            && (int) $order->store_id === (int) $this->storeContext->requireCurrent()->getKey()
            && $order->status === OrderStatus::Open;

        if (! $valid) {
            throw ValidationException::withMessages([
                'order' => 'Faqat joriy filialdagi ochiq buyurtmadan mahsulot ayirish mumkin.',
            ]);
        }
    }
}
