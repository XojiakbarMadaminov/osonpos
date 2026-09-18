<?php

namespace App\Actions\Orders;

use App\Actions\Payments\RecalculatePaymentStatus;
use App\Domain\Shift\CurrentShift;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetOpenOrderDiscount
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly CurrentShift $currentShift,
        private readonly RecalculateOrderTotals $recalculateTotals,
        private readonly RecalculatePaymentStatus $recalculatePaymentStatus,
    ) {}

    public function execute(Order $order, User $user, ?DiscountType $type, ?int $value): Order
    {
        return DB::transaction(function () use ($order, $user, $type, $value): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureCurrentOpenOrder($order);

            if (! $this->currentShift->for($user)) {
                throw ValidationException::withMessages([
                    'shift' => 'Chegirma qo‘llash uchun avval smenani oching.',
                ]);
            }

            if ($type === DiscountType::Percentage && ($value === null || $value < 1 || $value > 100)) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Foiz chegirmasi 1 dan 100 gacha bo‘lishi kerak.',
                ]);
            }

            if ($type === DiscountType::Fixed && ($value === null || $value < 1 || $value > $order->subtotal)) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Chegirma summasi mahsulotlar oralig‘idan oshmasligi kerak.',
                ]);
            }

            $order->forceFill([
                'discount_type' => $type,
                'discount_value' => $type ? $value : null,
                'discount_amount' => 0,
            ]);
            $this->recalculateTotals->execute($order);

            $paidAmount = (int) $order->payments()->sum('amount');
            if ($order->total < $paidAmount) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Chegirmadan keyingi jami summa avval to‘langan summadan kam bo‘lishi mumkin emas.',
                ]);
            }

            $this->recalculatePaymentStatus->execute($order);

            return $order;
        });
    }

    private function ensureCurrentOpenOrder(Order $order): void
    {
        if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
            || $order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages([
                'order' => 'Faqat joriy filialdagi ochiq buyurtmaga chegirma qo‘llash mumkin.',
            ]);
        }
    }
}
