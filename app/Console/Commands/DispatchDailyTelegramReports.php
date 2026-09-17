<?php

namespace App\Console\Commands;

use App\Domain\Subscription\SubscriptionAccess;
use App\Jobs\SendDailyTelegramReport;
use App\Models\DailyTelegramReport;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchDailyTelegramReports extends Command
{
    protected $signature = 'telegram:dispatch-daily-reports';

    protected $description = 'Filiallarning avvalgi kun Telegram hisobotlarini navbatga qo‘yish';

    public function handle(SubscriptionAccess $subscriptions): int
    {
        if (blank(config('services.telegram.bot_token'))) {
            return self::SUCCESS;
        }

        $queued = 0;

        Store::query()
            ->with(['organization.telegramSetting'])
            ->where('is_active', true)
            ->chunkById(100, function ($stores) use ($subscriptions, &$queued): void {
                foreach ($stores as $store) {
                    $localNow = now($store->timezone);
                    $organization = $store->organization;

                    if ($localNow->format('H') !== '00'
                        || ! in_array((int) $localNow->format('i'), range(5, 15), true)
                        || ! $organization->telegramSetting
                        || ! $subscriptions->hasFeature($organization, 'telegram_payment_notifications')) {
                        continue;
                    }

                    $businessDate = $localNow->subDay()->toDateString();
                    $inserted = DB::table('daily_telegram_reports')->insertOrIgnore([
                        'organization_id' => $organization->getKey(),
                        'store_id' => $store->getKey(),
                        'business_date' => $businessDate,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($inserted !== 1) {
                        continue;
                    }

                    $report = DailyTelegramReport::query()
                        ->where('store_id', $store->getKey())
                        ->where('business_date', $businessDate)
                        ->sole();

                    try {
                        SendDailyTelegramReport::dispatch($report->getKey());
                        $queued++;
                    } catch (Throwable $exception) {
                        $report->delete();
                        Log::warning('Daily Telegram report could not be queued.', [
                            'store_id' => $store->getKey(),
                            'business_date' => $businessDate,
                            'exception' => $exception::class,
                        ]);
                    }
                }
            });

        $this->info("{$queued} ta kunlik Telegram hisoboti navbatga qo‘yildi.");

        return self::SUCCESS;
    }
}
