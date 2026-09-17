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
use Illuminate\Validation\ValidationException;

class CreateMixedPayment
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
        private readonly RecalculatePaymentStatus $recalculate,
        private readonly CurrentShift $currentShift,
        private readonly PaymentTelegramNotifier $telegramNotifier,
    ) {}

    /** @return array{cash: Payment, card: Payment} */
    public function execute(
        Order $order,
        User $user,
        string $cashPaymentId,
        int $cashAmount,
        string $cardPaymentId,
        int $cardAmount,
    ): array {
        $created = [];

        $payments = DB::transaction(function () use (
            $order,
            $user,
            $cashPaymentId,
            $cashAmount,
            $cardPaymentId,
            $cardAmount,
            &$created,
        ): array {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureCurrentOpenOrder($order);
            $shift = $this->currentShift->for($user);
            if (! $shift) {
                throw ValidationException::withMessages([
                    'shift' => 'To‘lovni qabul qilish uchun avval smenani oching.',
                ]);
            }

            $definitions = [
                'cash' => [$cashPaymentId, PaymentMethod::Cash, $cashAmount],
                'card' => [$cardPaymentId, PaymentMethod::Card, $cardAmount],
            ];
            $existing = Payment::query()
                ->whereIn('id', [$cashPaymentId, $cardPaymentId])
                ->get()
                ->keyBy('id');

            if ($existing->isNotEmpty()) {
                if ($existing->count() !== 2) {
                    throw ValidationException::withMessages([
                        'payment' => 'Aralash to‘lov to‘liq saqlanmagan. Yangi urinishni boshlang.',
                    ]);
                }

                foreach ($definitions as [$id, $method, $amount]) {
                    $payment = $existing->get($id);
                    if (! $payment
                        || $payment->order_id !== $order->getKey()
                        || $payment->method !== $method
                        || $payment->amount !== $amount) {
                        throw ValidationException::withMessages([
                            'payment' => 'To‘lov identifikatori boshqa to‘lovga tegishli.',
                        ]);
                    }
                }

                return [
                    'cash' => $existing->get($cashPaymentId),
                    'card' => $existing->get($cardPaymentId),
                ];
            }

            $remaining = max(0, (int) $order->total - (int) $order->payments()->sum('amount'));
            if ($cashAmount + $cardAmount !== $remaining) {
                throw ValidationException::withMessages([
                    'payment' => 'Naqd va karta summasi buyurtmaning qolgan summasiga teng bo‘lishi kerak.',
                ]);
            }

            $result = [];
            foreach ($definitions as $key => [$id, $method, $amount]) {
                $payment = new Payment(['method' => $method, 'amount' => $amount]);
                $payment->setAttribute('id', $id);
                $payment->organization()->associate($order->organization_id);
                $payment->store()->associate($order->store_id);
                $payment->order()->associate($order);
                $payment->device()->associate($this->deviceContext->current());
                $payment->shift()->associate($shift);
                $payment->creator()->associate($user);
                $payment->save();
                $created[] = $payment;
                $result[$key] = $payment;
            }

            $this->recalculate->execute($order);

            return $result;
        });

        if (count($created) === 2) {
            $this->telegramNotifier->queueMixed($payments['cash'], $payments['card']);
        }

        return $payments;
    }

    private function ensureCurrentOpenOrder(Order $order): void
    {
        if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
            || $order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages([
                'order' => 'To‘lov uchun joriy filialda ochiq buyurtma bo‘lishi kerak.',
            ]);
        }
    }
}
