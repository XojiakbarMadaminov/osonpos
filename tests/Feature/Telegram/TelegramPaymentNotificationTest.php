<?php

use App\Actions\Organizations\AssignOrganizationOwner;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Subscription\SubscriptionAccess;
use App\Enums\DiscountType;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Filament\Admin\Pages\TelegramSettings;
use App\Jobs\SendTelegramPaymentNotification;
use App\Models\Device;
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
use Filament\Facades\Filament;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

function telegramFeatureSubscription(Organization $organization): Subscription
{
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($organization)->for($plan)->create();
    $feature = Feature::query()->where('code', 'telegram_payment_notifications')->sole();
    $subscription->features()->attach($feature);

    return $subscription;
}

function telegramSetting(Organization $organization, string $groupChatId = '-1001234567890'): TelegramSetting
{
    $settings = new TelegramSetting(['group_chat_id' => $groupChatId]);
    $settings->organization()->associate($organization);
    $settings->save();

    return $settings;
}

function telegramPaymentContext(): array
{
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $cashier = User::factory()->create();
    $organization->users()->attach($cashier);
    $store->users()->attach($cashier);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $cashier,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Cashier->value),
    );
    telegramFeatureSubscription($organization);
    telegramSetting($organization);
    $device = Device::factory()->for($organization)->for($store)->create();
    Shift::factory()->for($organization)->for($store)->for($device)->for($cashier)->create();
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($cashier, 'creator')->create([
        'display_number' => '#0042',
        'subtotal' => 100000,
        'total' => 100000,
    ]);

    return [$organization, $store, $cashier, $order, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
    ]];
}

beforeEach(function () {
    config()->set('services.telegram.bot_token', 'test-bot-token');
});

it('allows a feature to be attached to and removed from one subscription', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($organization)->for($plan)->create();
    Subscription::factory()->for($otherOrganization)->for($plan)->create();
    $feature = Feature::query()->where('code', 'telegram_payment_notifications')->sole();
    $access = app(SubscriptionAccess::class);

    expect($access->hasFeature($organization, $feature->code))->toBeFalse();

    $subscription->features()->attach($feature);

    expect($access->hasFeature($organization, $feature->code))->toBeTrue()
        ->and($access->hasFeature($otherOrganization, $feature->code))->toBeFalse();

    $subscription->features()->detach($feature);

    expect($access->hasFeature($organization, $feature->code))->toBeFalse();
});

it('shows and saves tenant telegram settings only with feature and permission', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = User::factory()->create();
    $store->users()->attach($owner);
    app(AssignOrganizationOwner::class)->execute($organization, $owner);
    telegramFeatureSubscription($organization);
    telegramSetting(Organization::factory()->create(), '-1009999999999');

    $this->actingAs($owner)->withSession([
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/telegram-settings')
        ->assertOk()
        ->assertSee('Telegram guruh ID si');

    Livewire::test(TelegramSettings::class)
        ->set('groupChatId', '-1001111222233')
        ->call('save')
        ->assertHasNoErrors();

    expect(TelegramSetting::query()->where('organization_id', $organization->id)->sole()->group_chat_id)
        ->toBe('-1001111222233');

    $subscription = $organization->subscriptions()->sole();
    $subscription->features()->detach();

    $this->get('/admin/telegram-settings')->assertForbidden();
});

it('queues one notification for a new payment and none for an idempotent replay', function () {
    Queue::fake();
    [, , $cashier, $order, $session] = telegramPaymentContext();
    $paymentId = (string) Str::ulid();
    $payload = [
        'id' => $paymentId,
        'method' => PaymentMethod::Cash->value,
        'amount' => 40000,
    ];

    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/payments", $payload)
        ->assertCreated();
    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/payments", $payload)
        ->assertOk();

    Queue::assertPushed(SendTelegramPaymentNotification::class, 1);
    Queue::assertPushed(fn (SendTelegramPaymentNotification $job): bool => $job->paymentId === $paymentId);
    expect(Payment::query()->count())->toBe(1);
});

it('queues one combined notification for an idempotent mixed payment', function () {
    Queue::fake();
    [, , $cashier, $order, $session] = telegramPaymentContext();
    $cashPaymentId = (string) Str::ulid();
    $cardPaymentId = (string) Str::ulid();
    $payload = [
        'cash_payment_id' => $cashPaymentId,
        'cash_amount' => 30000,
        'card_payment_id' => $cardPaymentId,
        'card_amount' => 70000,
    ];

    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/mixed-payments", $payload)
        ->assertCreated();
    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/mixed-payments", $payload)
        ->assertOk();

    Queue::assertPushed(SendTelegramPaymentNotification::class, 1);
    Queue::assertPushed(fn (SendTelegramPaymentNotification $job): bool => $job->paymentId === $cashPaymentId
        && $job->mixedPaymentId === $cardPaymentId);
});

it('does not queue notifications without both the feature and group setting', function () {
    Queue::fake();
    [$organization, , $cashier, $order, $session] = telegramPaymentContext();
    $organization->telegramSetting()->delete();

    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/payments", [
            'method' => PaymentMethod::Cash->value,
            'amount' => 20000,
        ])
        ->assertCreated();

    Queue::assertNothingPushed();

    telegramSetting($organization);
    $organization->subscriptions()->sole()->features()->detach();
    $secondOrder = Order::factory()
        ->for($organization)
        ->for($order->store)
        ->for($order->device)
        ->for($cashier, 'creator')
        ->create(['subtotal' => 30000, 'total' => 30000]);

    $this->actingAs($cashier)->withSession($session)
        ->postJson("/api/pos/orders/{$secondOrder->id}/payments", [
            'method' => PaymentMethod::Cash->value,
            'amount' => 30000,
        ])
        ->assertCreated();

    Queue::assertNothingPushed();
});

it('sends an Uzbek payment message to the organization group and marks delivery', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $organization = Organization::factory()->create(['name' => 'Oqtepa tashkiloti']);
    $store = Store::factory()->for($organization)->create(['name' => 'Chilonzor']);
    $cashier = User::factory()->create(['name' => 'Ali Kassir']);
    $order = Order::factory()->for($organization)->for($store)->for($cashier, 'creator')->create([
        'display_number' => '#0042',
        'customer_name' => 'Muhammadjon Olcha',
        'subtotal' => 100000,
        'discount_type' => DiscountType::Percentage,
        'discount_value' => 10,
        'discount_amount' => 10000,
        'total' => 90000,
    ]);
    OrderItem::factory()->for($organization)->for($store)->for($order)->for($cashier, 'creator')->create([
        'product_name' => 'Sinov lavash',
        'quantity' => 2,
        'unit_price' => 50000,
        'total' => 100000,
    ]);
    $payment = Payment::factory()->for($organization)->for($store)->for($order)->for($cashier, 'creator')->create([
        'method' => PaymentMethod::Card,
        'amount' => 40000,
    ]);
    telegramFeatureSubscription($organization);
    telegramSetting($organization, '-1007777888899');

    (new SendTelegramPaymentNotification($payment->id))->handle(app(SubscriptionAccess::class));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
        && $request['chat_id'] === '-1007777888899'
        && str_contains($request['text'], '🧾 Yangi sotuv!')
        && str_contains($request['text'], '#️⃣ Sotuv ID: 0042')
        && str_contains($request['text'], '🏪 Do‘kon: Chilonzor')
        && str_contains($request['text'], '👤 Mijoz: Muhammadjon Olcha')
        && str_contains($request['text'], '🧑‍💼 Kassir: Ali Kassir')
        && ! str_contains($request['text'], '💰 Summasi:')
        && ! str_contains($request['text'], '💰 Chegirmadan oldin:')
        && str_contains($request['text'], '🍽 Buyurtma turi: Olib ketish')
        && str_contains($request['text'], '💳 To‘lov turi: Karta')
        && str_contains($request['text'], "Sinov lavash\n2 x 50 000 = 100 000 so‘m")
        && str_contains($request['text'], 'Jami mahsulotlar: 2 dona')
        && str_contains($request['text'], "JAMI SUMMA: 100 000 so‘m\n🏷 Chegirma: 10% (−10 000 so‘m)\n💵 To‘langan: 40 000 so‘m")
        && str_contains($request['text'], '⏰ Sana:'));
    expect($payment->refresh()->telegram_notified_at)->not->toBeNull();
});

it('sends one mixed Telegram message with separate cash and card amounts', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create(['name' => 'Maryam shop']);
    $cashier = User::factory()->create();
    $order = Order::factory()->for($organization)->for($store)->for($cashier, 'creator')->create([
        'display_number' => '#0080',
        'subtotal' => 80000,
        'total' => 80000,
    ]);
    OrderItem::factory()->for($organization)->for($store)->for($order)->for($cashier, 'creator')->create([
        'product_name' => 'Test mahsulot',
        'quantity' => 1,
        'unit_price' => 80000,
        'total' => 80000,
    ]);
    $cashPayment = Payment::factory()->for($organization)->for($store)->for($order)->for($cashier, 'creator')->create([
        'method' => PaymentMethod::Cash,
        'amount' => 30000,
    ]);
    $cardPayment = Payment::factory()->for($organization)->for($store)->for($order)->for($cashier, 'creator')->create([
        'method' => PaymentMethod::Card,
        'amount' => 50000,
    ]);
    telegramFeatureSubscription($organization);
    telegramSetting($organization);

    (new SendTelegramPaymentNotification($cashPayment->id, $cardPayment->id))
        ->handle(app(SubscriptionAccess::class));

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_contains($request['text'], '💳 To‘lov turi: Naqd + Karta')
        && str_contains($request['text'], '💵 Naqd: 30 000 so‘m')
        && str_contains($request['text'], '💳 Karta: 50 000 so‘m'));
    expect($cashPayment->refresh()->telegram_notified_at)->not->toBeNull()
        ->and($cardPayment->refresh()->telegram_notified_at)->not->toBeNull();
});

it('does not roll back a saved payment when telegram delivery fails', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $cashier = User::factory()->create();
    $order = Order::factory()->for($organization)->for($store)->for($cashier, 'creator')->create(['total' => 50000]);
    $payment = Payment::factory()->for($organization)->for($store)->for($order)->for($cashier, 'creator')->create([
        'amount' => 50000,
    ]);
    telegramFeatureSubscription($organization);
    telegramSetting($organization);

    expect(fn () => (new SendTelegramPaymentNotification($payment->id))->handle(app(SubscriptionAccess::class)))
        ->toThrow(RequestException::class);

    expect(Payment::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and($payment->refresh()->telegram_notified_at)->toBeNull();
});
