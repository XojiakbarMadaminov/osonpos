<?php

namespace App\Actions\Payments;

use App\Domain\Shift\CurrentShift;
use App\Domain\Telegram\PaymentTelegramNotifier;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePayment
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
        private readonly RecalculatePaymentStatus $recalculate,
        private readonly CurrentShift $currentShift,
        private readonly PaymentTelegramNotifier $telegramNotifier,
    ) {}

    public function execute(
        Order $order,
        User $user,
        PaymentMethod $method,
        int $amount,
        ?string $id = null,
    ): Payment {
        $created = false;
        $payment = DB::transaction(function () use ($order, $user, $method, $amount, $id, &$created): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureCurrentOpenOrder($order);
            $shift = $this->currentShift->for($user);
            if (! $shift) {
                throw ValidationException::withMessages([
                    'shift' => 'To‘lovni qabul qilish uchun avval smenani oching.',
                ]);
            }
            $id ??= (string) Str::ulid();
            $existing = Payment::query()->whereKey($id)->first();

            if ($existing) {
                if (! $existing->order()->whereKey($order->getKey())->exists()) {
                    throw ValidationException::withMessages(['id' => 'To‘lov identifikatori boshqa buyurtmaga tegishli.']);
                }

                return $existing;
            }

            $payment = new Payment(['method' => $method, 'amount' => $amount]);
            $payment->setAttribute('id', $id);
            $payment->organization()->associate($order->organization_id);
            $payment->store()->associate($order->store_id);
            $payment->order()->associate($order);
            $payment->device()->associate($this->deviceContext->current());
            $payment->shift()->associate($shift);
            $payment->creator()->associate($user);
            $payment->save();
            $created = true;

            $this->recalculate->execute($order);

            return $payment;
        });

        if ($created) {
            $this->telegramNotifier->queue($payment);
        }

        return $payment;
    }

    private function ensureCurrentOpenOrder(Order $order): void
    {
        if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
            || $order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages(['order' => 'To‘lov uchun joriy filialda ochiq buyurtma bo‘lishi kerak.']);
        }
    }
}
