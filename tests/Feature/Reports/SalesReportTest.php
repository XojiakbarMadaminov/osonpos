<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Reports\SalesReport;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;

function completedSale(
    Organization $organization,
    Store $store,
    User $user,
    int $total,
    PaymentMethod $method,
    OrderType $type,
    string $product,
    int $quantity,
): Order {
    $order = Order::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'status' => OrderStatus::Completed,
        'type' => $type,
        'subtotal' => $total,
        'total' => $total,
        'opened_at' => now(),
        'closed_at' => now(),
        'closed_by' => $user->id,
    ]);
    Payment::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create([
        'method' => $method,
        'amount' => $total,
    ]);
    OrderItem::factory()->for($organization)->for($store)->for($order)->for($user, 'creator')->create([
        'product_name' => $product,
        'quantity' => $quantity,
        'unit_price' => intdiv($total, $quantity),
        'total' => $total,
    ]);

    return $order;
}

it('calculates revenue average check and grouped breakdowns', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    completedSale($organization, $store, $user, 60000, PaymentMethod::Cash, OrderType::DineIn, 'Burger', 2);
    completedSale($organization, $store, $user, 40000, PaymentMethod::Card, OrderType::Takeaway, 'Cola', 4);
    Expense::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'type' => ExpenseType::ProductCost,
        'amount' => 25000,
    ]);
    Expense::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'type' => ExpenseType::Rent,
        'amount' => 10000,
    ]);
    Expense::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'amount' => 50000,
        'status' => ExpenseStatus::Cancelled,
        'cancelled_by' => $user->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Bekor qilingan',
    ]);

    $report = app(SalesReport::class)->generate(
        $organization,
        CarbonImmutable::now()->subDay(),
        CarbonImmutable::now()->addDay(),
        [$store->id],
    );

    expect($report['revenue'])->toBe(100000)
        ->and($report['expense_total'])->toBe(35000)
        ->and($report['order_count'])->toBe(2)
        ->and($report['average_check'])->toBe(50000)
        ->and($report['payment_breakdown'])->toContain(['label' => PaymentMethod::Cash->getLabel(), 'total' => 60000])
        ->and($report['payment_breakdown'])->toContain(['label' => PaymentMethod::Card->getLabel(), 'total' => 40000])
        ->and($report['order_type_breakdown'])->toContain(['label' => OrderType::DineIn->getLabel(), 'total' => 1])
        ->and($report['expense_breakdown'])->toContain(['label' => ExpenseType::ProductCost->getLabel(), 'total' => 25000])
        ->and($report['expense_breakdown'])->toContain(['label' => ExpenseType::Rent->getLabel(), 'total' => 10000]);
});

it('ranks top products by sold quantity', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    completedSale($organization, $store, $user, 30000, PaymentMethod::Cash, OrderType::Takeaway, 'Burger', 3);
    completedSale($organization, $store, $user, 20000, PaymentMethod::Cash, OrderType::Takeaway, 'Tea', 1);

    $report = app(SalesReport::class)->generate(
        $organization,
        CarbonImmutable::now()->subDay(),
        CarbonImmutable::now()->addDay(),
        [$store->id],
    );

    expect($report['top_products'][0])->toMatchArray([
        'name' => 'Burger',
        'quantity' => 3,
        'revenue' => 30000,
    ]);
});

it('applies store filters without mixing tenant data', function () {
    $organization = Organization::factory()->create();
    $firstStore = Store::factory()->for($organization)->create();
    $secondStore = Store::factory()->for($organization)->create();
    $foreignOrganization = Organization::factory()->create();
    $foreignStore = Store::factory()->for($foreignOrganization)->create();
    $user = User::factory()->create();
    completedSale($organization, $firstStore, $user, 10000, PaymentMethod::Cash, OrderType::Takeaway, 'Local A', 1);
    completedSale($organization, $secondStore, $user, 20000, PaymentMethod::Cash, OrderType::Takeaway, 'Local B', 1);
    completedSale($foreignOrganization, $foreignStore, $user, 900000, PaymentMethod::Cash, OrderType::Takeaway, 'Foreign', 1);
    Expense::factory()->for($organization)->for($firstStore)->for($user, 'creator')->create(['amount' => 1000]);
    Expense::factory()->for($organization)->for($secondStore)->for($user, 'creator')->create(['amount' => 2000]);
    Expense::factory()->for($foreignOrganization)->for($foreignStore)->for($user, 'creator')->create(['amount' => 900000]);
    $from = CarbonImmutable::now()->subDay();
    $to = CarbonImmutable::now()->addDay();

    $firstOnly = app(SalesReport::class)->generate($organization, $from, $to, [$firstStore->id]);
    $allLocal = app(SalesReport::class)->generate($organization, $from, $to, [$firstStore->id, $secondStore->id]);

    expect($firstOnly['revenue'])->toBe(10000)
        ->and($firstOnly['expense_total'])->toBe(1000)
        ->and($firstOnly['order_count'])->toBe(1)
        ->and($allLocal['revenue'])->toBe(30000)
        ->and($allLocal['expense_total'])->toBe(3000)
        ->and(collect($allLocal['top_products'])->pluck('name')->all())->not->toContain('Foreign');
});

it('filters expenses by their incurred date', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    Expense::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'amount' => 12000,
        'incurred_on' => '2026-09-14',
    ]);
    Expense::factory()->for($organization)->for($store)->for($user, 'creator')->create([
        'amount' => 99000,
        'incurred_on' => '2026-09-13',
    ]);

    $report = app(SalesReport::class)->generate(
        $organization,
        CarbonImmutable::parse('2026-09-13 19:00:00 UTC'),
        CarbonImmutable::parse('2026-09-14 18:59:59 UTC'),
        [$store->id],
        CarbonImmutable::parse('2026-09-14'),
        CarbonImmutable::parse('2026-09-14'),
    );

    expect($report['expense_total'])->toBe(12000);
});

it('allows a manager with reports permission to open bounded reports', function () {
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $manager = User::factory()->create();
    $organization->users()->attach($manager);
    $store->users()->attach($manager);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $manager,
        $organization,
        fn (User $user) => $user->assignRole(OrganizationRole::Manager->value),
    );

    $this->actingAs($manager)
        ->withSession(['current_organization_id' => $organization->id])
        ->get('/admin/reports')
        ->assertOk()
        ->assertSee('O‘rtacha chek')
        ->assertSee('Chiqimlar')
        ->assertSee('Chiqim turlari bo‘yicha');
});
