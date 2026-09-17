<?php

use App\Enums\ExpenseType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Jobs\SendDailyTelegramReport;
use App\Models\DailyTelegramReport;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Feature;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\TelegramSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function dailyReportOrganization(string $timezone = 'Asia/Tashkent'): array
{
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['timezone' => $timezone]);
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($organization)->for($plan)->create();
    $subscription->features()->attach(
        Feature::query()->where('code', 'telegram_payment_notifications')->sole(),
    );
    $settings = new TelegramSetting(['group_chat_id' => '-1001234567890']);
    $settings->organization()->associate($organization);
    $settings->save();

    return [$organization, $store];
}

function dailyReportRecord(Organization $organization, Store $store, string $date): DailyTelegramReport
{
    $report = new DailyTelegramReport(['business_date' => $date]);
    $report->organization()->associate($organization);
    $report->store()->associate($store);
    $report->save();

    return $report;
}

beforeEach(function () {
    config()->set('services.telegram.bot_token', 'daily-report-token');
});

it('queues the previous day report once at 00:05 in each store timezone', function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-09-18 00:05:00', 'Asia/Tashkent'));
    [, $tashkentStore] = dailyReportOrganization();
    [, $newYorkStore] = dailyReportOrganization('America/New_York');

    $this->artisan('telegram:dispatch-daily-reports')->assertSuccessful();
    $this->artisan('telegram:dispatch-daily-reports')->assertSuccessful();

    Queue::assertPushed(SendDailyTelegramReport::class, 1);
    expect(DailyTelegramReport::query()->count())->toBe(1)
        ->and(DailyTelegramReport::query()->sole()->store_id)->toBe($tashkentStore->id)
        ->and(DailyTelegramReport::query()->sole()->business_date->toDateString())->toBe('2026-09-17')
        ->and(DailyTelegramReport::query()->where('store_id', $newYorkStore->id)->exists())->toBeFalse();
});

it('does not queue a daily report without the subscription feature', function () {
    Queue::fake();
    $this->travelTo(CarbonImmutable::parse('2026-09-18 00:05:00', 'Asia/Tashkent'));
    $organization = Organization::factory()->create();
    Store::factory()->for($organization)->create(['timezone' => 'Asia/Tashkent']);
    Subscription::factory()->for($organization)->create();
    $settings = new TelegramSetting(['group_chat_id' => '-1009876543210']);
    $settings->organization()->associate($organization);
    $settings->save();

    $this->artisan('telegram:dispatch-daily-reports')->assertSuccessful();

    Queue::assertNothingPushed();
    expect(DailyTelegramReport::query()->exists())->toBeFalse();
});

it('sends the approved per-store daily report and marks it delivered', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $this->travelTo(CarbonImmutable::parse('2026-09-18 00:05:00', 'Asia/Tashkent'));
    [$organization, $store] = dailyReportOrganization();
    $cashier = User::factory()->create();
    $saleTime = CarbonImmutable::parse('2026-09-17 12:00:00', 'Asia/Tashkent');
    $order = Order::factory()->for($organization)->for($store)->for($cashier, 'creator')->create([
        'business_date' => '2026-09-17',
        'status' => OrderStatus::Completed,
        'type' => OrderType::Takeaway,
        'subtotal' => 100000,
        'total' => 100000,
        'opened_at' => $saleTime,
        'closed_at' => $saleTime,
        'closed_by' => $cashier->id,
    ]);
    Payment::factory()->for($organization)->for($store)->for($order)->for($cashier, 'creator')->create([
        'method' => PaymentMethod::Cash,
        'amount' => 100000,
    ]);
    OrderItem::factory()->for($organization)->for($store)->for($order)->for($cashier, 'creator')->create([
        'product_name' => 'Lavash',
        'quantity' => 2,
        'unit_price' => 50000,
        'unit_cost' => 20000,
        'total' => 100000,
    ]);
    Order::factory()->for($organization)->for($store)->for($cashier, 'creator')->create([
        'business_date' => '2026-09-17',
        'status' => OrderStatus::Cancelled,
        'opened_at' => $saleTime,
    ]);
    Expense::factory()->for($organization)->for($store)->for($cashier, 'creator')->create([
        'type' => ExpenseType::Other,
        'amount' => 10000,
        'incurred_on' => '2026-09-17',
    ]);
    $closedDevice = Device::factory()->for($organization)->for($store)->create();
    Shift::factory()->for($organization)->for($store)->for($closedDevice)->for($cashier)->create([
        'status' => ShiftStatus::Closed,
        'opened_at' => $saleTime->subHours(2),
        'closed_at' => $saleTime->addHours(2),
        'closing_cash' => 100000,
    ]);
    $openCashier = User::factory()->create();
    $openDevice = Device::factory()->for($organization)->for($store)->create();
    Shift::factory()->for($organization)->for($store)->for($openDevice)->for($openCashier)->create([
        'status' => ShiftStatus::Open,
        'opened_at' => $saleTime,
    ]);
    $report = dailyReportRecord($organization, $store, '2026-09-17');

    app()->call([new SendDailyTelegramReport($report->id), 'handle']);

    Http::assertSent(fn ($request): bool => $request['chat_id'] === '-1001234567890'
        && str_contains($request['text'], '📊 KUNLIK SAVDO HISOBOTI')
        && str_contains($request['text'], "🏪 Do‘kon: {$store->name}")
        && str_contains($request['text'], '📅 Sana: 17.09.2026')
        && str_contains($request['text'], 'Jami buyurtmalar: 1 ta')
        && str_contains($request['text'], 'Bekor qilingan: 1 ta')
        && str_contains($request['text'], 'Jami mahsulotlar: 2 dona')
        && str_contains($request['text'], 'Jami savdo: 100 000 so‘m')
        && str_contains($request['text'], 'Chiqimlar: 10 000 so‘m')
        && str_contains($request['text'], 'Taxminiy yalpi foyda: 60 000 so‘m')
        && str_contains($request['text'], 'Naqd: 100 000 so‘m')
        && str_contains($request['text'], 'Olib ketish: 1 ta')
        && str_contains($request['text'], '1. Lavash — 2 dona')
        && str_contains($request['text'], 'Yopilgan: 1 ta')
        && str_contains($request['text'], 'Ochiq qolgan: 1 ta')
        && str_contains($request['text'], '⚠️ Ochiq qolgan smena mavjud!'));
    expect($report->refresh()->sent_at)->not->toBeNull();
});
