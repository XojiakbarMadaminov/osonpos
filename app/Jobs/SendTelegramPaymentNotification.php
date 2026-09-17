<?php

namespace App\Jobs;

use App\Domain\Subscription\SubscriptionAccess;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\TelegramSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendTelegramPaymentNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $paymentId,
        public readonly ?string $mixedPaymentId = null,
    ) {}

    public function handle(SubscriptionAccess $subscriptions): void
    {
        $token = config('services.telegram.bot_token');
        if (blank($token)) {
            return;
        }

        $payment = Payment::query()
            ->with(['organization', 'store', 'order.items.removals', 'creator'])
            ->find($this->paymentId);

        if (! $payment) {
            return;
        }

        $mixedPayment = $this->mixedPaymentId
            ? Payment::query()->find($this->mixedPaymentId)
            : null;
        if ($this->mixedPaymentId && (! $mixedPayment
            || $mixedPayment->organization_id !== $payment->organization_id
            || $mixedPayment->order_id !== $payment->order_id
            || $payment->method !== PaymentMethod::Cash
            || $mixedPayment->method !== PaymentMethod::Card)) {
            return;
        }

        $paymentIds = array_values(array_filter([$payment->getKey(), $mixedPayment?->getKey()]));
        if ($payment->telegram_notified_at || $mixedPayment?->telegram_notified_at) {
            Payment::query()
                ->whereIn('id', $paymentIds)
                ->whereNull('telegram_notified_at')
                ->update(['telegram_notified_at' => now()]);

            return;
        }

        if (! $subscriptions->hasFeature($payment->organization, 'telegram_payment_notifications')) {
            return;
        }

        $settings = TelegramSetting::query()
            ->where('organization_id', $payment->organization_id)
            ->first();

        if (! $settings) {
            return;
        }

        $paidAmount = (int) $payment->order->payments()->sum('amount');

        Http::asJson()
            ->timeout(10)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $settings->group_chat_id,
                'text' => $this->message($payment, $paidAmount, $mixedPayment),
            ])
            ->throw();

        DB::transaction(function () use ($paymentIds): void {
            Payment::query()
                ->whereIn('id', $paymentIds)
                ->whereNull('telegram_notified_at')
                ->update(['telegram_notified_at' => now()]);
        });
    }

    private function message(Payment $payment, int $paidAmount, ?Payment $mixedPayment): string
    {
        $formatMoney = fn (int $amount): string => number_format($amount, 0, '.', ' ').' so‘m';
        $items = $payment->order->items
            ->filter(fn ($item): bool => $item->remainingQuantity() > 0);
        $productLines = $items
            ->flatMap(fn ($item): array => [
                $item->product_name,
                "{$item->remainingQuantity()} x ".number_format($item->unit_price, 0, '.', ' ')
                    .' = '.$formatMoney($item->remainingTotal()),
                '',
            ])
            ->all();
        $saleId = ltrim($payment->order->display_number, '#');
        $customer = $payment->order->customer_name ?: 'Ko‘rsatilmagan';
        $time = $payment->created_at
            ->setTimezone($payment->store->timezone)
            ->format('d.m.Y H:i');
        $paymentLines = $mixedPayment
            ? [
                '💳 To‘lov turi: Naqd + Karta',
                '💵 Naqd: '.$formatMoney($payment->amount),
                '💳 Karta: '.$formatMoney($mixedPayment->amount),
            ]
            : ["💳 To‘lov turi: {$payment->method->getLabel()}"];

        return implode("\n", [
            '🧾 Yangi sotuv!',
            "#️⃣ Sotuv ID: {$saleId}",
            "🏪 Do‘kon: {$payment->store->name}",
            "👤 Mijoz: {$customer}",
            "🧑‍💼 Kassir: {$payment->creator->name}",
            '💰 Summasi: '.$formatMoney($payment->order->total),
            "🍽 Buyurtma turi: {$payment->order->type->getLabel()}",
            ...$paymentLines,
            '📦 Mahsulotlar:',
            '---------------',
            '',
            ...$productLines,
            '------------------',
            '',
            'Jami mahsulotlar: '.$items->sum(fn ($item): int => $item->remainingQuantity()).' dona',
            'JAMI SUMMA: '.$formatMoney($payment->order->total),
            '💵 To‘langan: '.$formatMoney($paidAmount),
            "⏰ Sana: {$time}",
        ]);
    }
}
