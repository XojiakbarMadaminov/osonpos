<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddOrderItem
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function execute(Order $order, User $user, AddOrderItemData $data): OrderItem
    {
        return DB::transaction(function () use ($order, $user, $data): OrderItem {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureOpenCurrentOrder($order);
            $id = $data->id ?? (string) Str::ulid();
            $existing = OrderItem::query()->whereKey($id)->first();

            if ($existing) {
                if (! $existing->order()->whereKey($order->getKey())->exists()) {
                    throw ValidationException::withMessages(['id' => 'Mahsulot identifikatori boshqa buyurtmaga tegishli.']);
                }

                return $existing;
            }

            $product = Product::query()
                ->whereKey($data->productId)
                ->where('organization_id', $order->organization_id)
                ->where('is_active', true)
                ->first() ?? throw ValidationException::withMessages([
                    'product_id' => 'Joriy tashkilotdan faol mahsulotni tanlang.',
                ]);

            $item = new OrderItem(['note' => $data->note]);
            $item->setAttribute('id', $id);
            $item->organization()->associate($order->organization_id);
            $item->store()->associate($order->store_id);
            $item->order()->associate($order);
            $item->product()->associate($product);
            $item->creator()->associate($user);
            $item->forceFill([
                'product_name' => $product->name,
                'quantity' => $data->quantity,
                'unit_price' => $product->price,
                'total' => $product->price * $data->quantity,
            ])->save();

            $subtotal = (int) $order->items()->sum('total');
            $order->forceFill([
                'subtotal' => $subtotal,
                'total' => $subtotal + $order->delivery_fee,
            ])->save();

            return $item;
        });
    }

    private function ensureOpenCurrentOrder(Order $order): void
    {
        $valid = (int) $order->organization_id === (int) $this->tenantContext->requireCurrent()->getKey()
            && (int) $order->store_id === (int) $this->storeContext->requireCurrent()->getKey()
            && $order->status === OrderStatus::Open;

        if (! $valid) {
            throw ValidationException::withMessages(['order' => 'Faqat joriy filialdagi ochiq buyurtmani o‘zgartirish mumkin.']);
        }
    }
}
