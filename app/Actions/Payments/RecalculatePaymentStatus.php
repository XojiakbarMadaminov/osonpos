<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;

class RecalculatePaymentStatus
{
    public function execute(Order $order): array
    {
        $paidAmount = (int) $order->payments()->sum('amount');
        $remainingAmount = max(0, $order->total - $paidAmount);
        $status = match (true) {
            $paidAmount === 0 => PaymentStatus::Unpaid,
            $paidAmount < $order->total => PaymentStatus::PartiallyPaid,
            default => PaymentStatus::Paid,
        };

        $order->forceFill(['payment_status' => $status])->save();

        return [
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_status' => $status,
        ];
    }
}
