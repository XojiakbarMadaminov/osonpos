<?php

namespace App\Jobs;

use App\Domain\Reports\SalesReport;
use App\Domain\Subscription\SubscriptionAccess;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Models\DailyTelegramReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendDailyTelegramReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 86400;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $reportId) {}

    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    public function handle(SubscriptionAccess $subscriptions, SalesReport $salesReport): void
    {
        $token = config('services.telegram.bot_token');
        $reportRecord = DailyTelegramReport::query()
            ->with(['organization.telegramSetting', 'store'])
            ->find($this->reportId);

        if (blank($token) || ! $reportRecord || $reportRecord->sent_at) {
            return;
        }

        $organization = $reportRecord->organization;
        $settings = $organization->telegramSetting;
        if (! $settings || ! $subscriptions->hasFeature($organization, 'telegram_payment_notifications')) {
            return;
        }

        $store = $reportRecord->store;
        $businessDate = CarbonImmutable::parse($reportRecord->business_date->toDateString(), $store->timezone);
        $from = $businessDate->startOfDay()->utc();
        $to = $businessDate->endOfDay()->utc();
        $report = $salesReport->generate(
            $organization,
            $from,
            $to,
            [$store->getKey()],
            $businessDate,
            $businessDate,
        );

        $cancelledOrders = DB::table('orders')
            ->where('organization_id', $organization->getKey())
            ->where('store_id', $store->getKey())
            ->where('business_date', $businessDate->toDateString())
            ->where('status', OrderStatus::Cancelled->value)
            ->count();
        $closedShifts = DB::table('shifts')
            ->where('organization_id', $organization->getKey())
            ->where('store_id', $store->getKey())
            ->where('status', ShiftStatus::Closed->value)
            ->whereBetween('closed_at', [$from, $to])
            ->count();
        $openShifts = DB::table('shifts')
            ->where('organization_id', $organization->getKey())
            ->where('store_id', $store->getKey())
            ->where('status', ShiftStatus::Open->value)
            ->count();

        Http::asJson()
            ->timeout(10)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $settings->group_chat_id,
                'text' => $this->message($reportRecord, $report, $cancelledOrders, $closedShifts, $openShifts),
            ])
            ->throw();

        DailyTelegramReport::query()
            ->whereKey($reportRecord->getKey())
            ->whereNull('sent_at')
            ->update(['sent_at' => now()]);
    }

    private function message(
        DailyTelegramReport $record,
        array $report,
        int $cancelledOrders,
        int $closedShifts,
        int $openShifts,
    ): string {
        $money = fn (int $amount): string => number_format($amount, 0, '.', ' ').' so‘m';
        $payments = collect($report['payment_breakdown'])->keyBy('label');
        $orderTypes = collect($report['order_type_breakdown'])->keyBy('label');
        $lines = [
            '📊 KUNLIK SAVDO HISOBOTI',
            '',
            "🏪 Do‘kon: {$record->store->name}",
            '📅 Sana: '.$record->business_date->format('d.m.Y'),
            '⏰ Hisobot vaqti: 00:05',
            '',
            '🧾 SAVDOLAR',
            "Jami buyurtmalar: {$report['order_count']} ta",
            "Bekor qilingan: {$cancelledOrders} ta",
            "Jami mahsulotlar: {$report['item_count']} dona",
            '',
            '💰 MOLIYAVIY NATIJA',
            'Jami savdo: '.$money($report['revenue']),
            'Chiqimlar: '.$money($report['expense_total']),
            'Taxminiy yalpi foyda: '.$money($report['estimated_gross_profit']),
            'O‘rtacha chek: '.$money($report['average_check']),
            '',
            '💳 TO‘LOVLAR',
        ];

        foreach (PaymentMethod::cases() as $method) {
            $lines[] = $method->getLabel().': '.$money((int) ($payments->get($method->getLabel())['total'] ?? 0));
        }

        $lines[] = '';
        $lines[] = '🍽 BUYURTMA TURLARI';
        foreach (OrderType::cases() as $type) {
            $lines[] = $type->getLabel().': '.((int) ($orderTypes->get($type->getLabel())['total'] ?? 0)).' ta';
        }

        $lines[] = '';
        $lines[] = '🏆 ENG KO‘P SOTILGANLAR';
        if ($report['top_products'] === []) {
            $lines[] = 'Sotilgan mahsulotlar yo‘q.';
        } else {
            foreach (array_slice($report['top_products'], 0, 5) as $index => $product) {
                $lines[] = ($index + 1).". {$product['name']} — {$product['quantity']} dona";
            }
        }

        $lines[] = '';
        $lines[] = '👥 SMENALAR';
        $lines[] = "Yopilgan: {$closedShifts} ta";
        $lines[] = "Ochiq qolgan: {$openShifts} ta";

        if ($openShifts > 0) {
            $lines[] = '';
            $lines[] = '⚠️ Ochiq qolgan smena mavjud!';
        }

        return implode("\n", $lines);
    }
}
