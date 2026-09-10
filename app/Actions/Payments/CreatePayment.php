<?php

namespace App\Actions\Payments;

use App\Domain\Shift\CurrentShift;
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
    ) {}

    public function execute(
        Order $order,
        User $user,
        PaymentMethod $method,
        int $amount,
        ?string $id = null,
    ): Payment {
        return DB::transaction(function () use ($order, $user, $method, $amount, $id): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->ensureCurrentOpenOrder($order);
            if ($method === PaymentMethod::Cash && ! $this->currentShift->for($user)) {
                throw ValidationException::withMessages(['shift' => 'An active shift is required for cash payments.']);
            }
            $id ??= (string) Str::ulid();
            $existing = Payment::query()->whereKey($id)->first();

            if ($existing) {
                if (! $existing->order()->whereKey($order->getKey())->exists()) {
                    throw ValidationException::withMessages(['id' => 'The payment identifier belongs to another order.']);
                }

                return $existing;
            }

            $payment = new Payment(['method' => $method, 'amount' => $amount]);
            $payment->setAttribute('id', $id);
            $payment->organization()->associate($order->organization_id);
            $payment->store()->associate($order->store_id);
            $payment->order()->associate($order);
            $payment->device()->associate($this->deviceContext->current());
            $payment->creator()->associate($user);
            $payment->save();

            $this->recalculate->execute($order);

            return $payment;
        });
    }

    private function ensureCurrentOpenOrder(Order $order): void
    {
        if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
            || $order->status !== OrderStatus::Open) {
            throw ValidationException::withMessages(['order' => 'Payments require a current-store open order.']);
        }
    }
}
