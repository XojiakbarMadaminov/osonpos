<?php

namespace App\Domain\Telegram;

use App\Domain\Subscription\SubscriptionAccess;
use App\Jobs\SendTelegramPaymentNotification;
use App\Models\Payment;
use App\Models\TelegramSetting;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentTelegramNotifier
{
    public function __construct(private readonly SubscriptionAccess $subscriptions) {}

    public function queue(Payment $payment): void
    {
        if (! $this->canQueue($payment)) {
            return;
        }

        try {
            SendTelegramPaymentNotification::dispatch($payment->getKey());
        } catch (Throwable $exception) {
            Log::warning('Telegram payment notification could not be queued.', [
                'payment_id' => $payment->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    public function queueMixed(Payment $cashPayment, Payment $cardPayment): void
    {
        if (! $this->canQueue($cashPayment)) {
            return;
        }

        try {
            SendTelegramPaymentNotification::dispatch(
                $cashPayment->getKey(),
                $cardPayment->getKey(),
            );
        } catch (Throwable $exception) {
            Log::warning('Mixed Telegram payment notification could not be queued.', [
                'cash_payment_id' => $cashPayment->getKey(),
                'card_payment_id' => $cardPayment->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    private function canQueue(Payment $payment): bool
    {
        return filled(config('services.telegram.bot_token'))
            && $this->subscriptions->hasFeature($payment->organization, 'telegram_payment_notifications')
            && TelegramSetting::query()->where('organization_id', $payment->organization_id)->exists();
    }
}
