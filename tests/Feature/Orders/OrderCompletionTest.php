<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Actions\Payments\RecalculatePaymentStatus;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Store\TableOccupancy;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\OrganizationRole;
use App\Enums\PaymentStatus;
use App\Enums\PrintType;
use App\Models\Device;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\User;

function completionContext(OrderType $type = OrderType::Takeaway): array
{
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $manager = User::factory()->create();
    $organization->users()->attach($manager);
    $store->users()->attach($manager);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $manager,
        $organization,
        fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Manager->value),
    );
    Subscription::factory()->for($organization)->create();
    $device = Device::factory()->for($organization)->for($store)->create();
    $table = $type === OrderType::DineIn ? Table::factory()->for($organization)->for($store)->create() : null;
    $order = Order::factory()->for($organization)->for($store)->for($device)->for($manager, 'creator')->create([
        'type' => $type,
        'table_id' => $table?->id,
        'subtotal' => 65000,
        'total' => 65000,
    ]);

    return [$organization, $store, $manager, $device, $table, $order, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        'current_device_id' => $device->id,
    ]];
}

function payCompletionOrder(Order $order, Organization $organization, Store $store, User $user): void
{
    Payment::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create([
        'amount' => $order->total,
    ]);
    app(RecalculatePaymentStatus::class)->execute($order);
}

function receiptPrinter(Organization $organization, Store $store, Device $device, string $name = 'POS Printer'): Printer
{
    $printer = Printer::factory()->for($organization)->for($store)->for($device)->create([
        'name' => $name,
        'system_name' => $name,
    ]);
    PrintRoute::factory()->for($organization)->for($store)->for($printer)->create([
        'print_type' => PrintType::CustomerReceipt,
    ]);

    return $printer;
}

it('completes a paid dine-in order and frees its table', function () {
    [$organization, $store, $manager, , $table, $order, $session] = completionContext(OrderType::DineIn);
    payCompletionOrder($order, $organization, $store, $manager);

    expect(app(TableOccupancy::class)->isOccupied($table))->toBeTrue();
    $this->actingAs($manager)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Completed->value);

    expect(app(TableOccupancy::class)->isOccupied($table))->toBeFalse()
        ->and($order->refresh()->closed_by)->toBe($manager->id);
});

it('requires full payment before completing takeaway or delivery', function (OrderType $type) {
    [$organization, $store, $manager, , , $order, $session] = completionContext($type);

    $this->actingAs($manager)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/complete")
        ->assertUnprocessable();
    expect($order->refresh()->status)->toBe(OrderStatus::Open)
        ->and($order->payment_status)->toBe(PaymentStatus::Unpaid);

    payCompletionOrder($order, $organization, $store, $manager);
    $this->actingAs($manager)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/complete")
        ->assertOk();
})->with([OrderType::Takeaway, OrderType::Delivery]);

it('resolves customer receipts through the configurable receipt route', function () {
    [$organization, $store, $manager, $device, , $order, $session] = completionContext();
    payCompletionOrder($order, $organization, $store, $manager);
    $firstPrinter = receiptPrinter($organization, $store, $device, 'Shared POS Printer');

    $this->actingAs($manager)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/receipt")
        ->assertOk()
        ->assertJsonPath('data.printer.id', $firstPrinter->id);

    $secondPrinter = Printer::factory()->for($organization)->for($store)->for($device)->create([
        'name' => 'Receipt Only',
        'system_name' => 'Receipt Only',
    ]);
    PrintRoute::query()->where('print_type', PrintType::CustomerReceipt)->firstOrFail()
        ->printer()->associate($secondPrinter)->save();

    $this->actingAs($manager)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/receipt")
        ->assertOk()
        ->assertJsonPath('data.printer.id', $secondPrinter->id);
});

it('keeps completed order and payment state when receipt printing happens later', function () {
    [$organization, $store, $manager, $device, , $order, $session] = completionContext();
    payCompletionOrder($order, $organization, $store, $manager);
    receiptPrinter($organization, $store, $device);

    $this->actingAs($manager)->withSession($session)->postJson("/api/pos/orders/{$order->id}/complete")->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payments()->count())->toBe(1);
});

it('marks receipt reprints without changing the completed order', function () {
    [$organization, $store, $manager, $device, , $order, $session] = completionContext();
    payCompletionOrder($order, $organization, $store, $manager);
    receiptPrinter($organization, $store, $device);
    $this->actingAs($manager)->withSession($session)->postJson("/api/pos/orders/{$order->id}/complete")->assertOk();
    $closedAt = $order->refresh()->closed_at;

    $this->actingAs($manager)->withSession($session)
        ->postJson("/api/pos/orders/{$order->id}/receipt/reprint")
        ->assertOk()
        ->assertJsonPath('data.is_reprint', true);

    expect($order->refresh()->closed_at->equalTo($closedAt))->toBeTrue();
});
