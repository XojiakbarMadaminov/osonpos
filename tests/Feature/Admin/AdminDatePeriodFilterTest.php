<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Enums\ShiftStatus;
use App\Filament\Admin\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Admin\Resources\Orders\Pages\ListOrders;
use App\Filament\Admin\Resources\Shifts\Pages\ListShifts;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

function dateFilterContext(): array
{
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner);
    $store->users()->attach($owner);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $owner,
        $organization,
        fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Owner->value),
    );
    app(TenantContext::class)->resolveFor($owner, $organization->id);

    test()->actingAs($owner)->withSession([
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return [$organization, $store, $owner];
}

it('defaults orders to today and supports week month and custom periods', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 12:00:00', 'Asia/Tashkent'));
    [$organization, $store, $owner] = dateFilterContext();
    $today = Order::factory()->for($organization)->for($store)->for($owner, 'creator')->create([
        'display_number' => '#0001',
        'business_date' => '2026-09-16',
    ]);
    $thisWeek = Order::factory()->for($organization)->for($store)->for($owner, 'creator')->create([
        'display_number' => '#0001',
        'business_date' => '2026-09-14',
    ]);
    $thisMonth = Order::factory()->for($organization)->for($store)->for($owner, 'creator')->create([
        'display_number' => '#0001',
        'business_date' => '2026-09-01',
    ]);
    $previousMonth = Order::factory()->for($organization)->for($store)->for($owner, 'creator')->create([
        'display_number' => '#0001',
        'business_date' => '2026-08-31',
    ]);
    $foreign = Order::factory()->create(['business_date' => '2026-09-16']);

    $component = Livewire::test(ListOrders::class)
        ->assertSeeInOrder(['Bugun', 'Hafta', 'Oy', 'Oraliq'])
        ->assertCanSeeTableRecords([$today])
        ->assertCanNotSeeTableRecords([$thisWeek, $thisMonth, $previousMonth, $foreign]);

    $component->filterTable('date_period', ['period' => 'WEEK'])
        ->assertCanSeeTableRecords([$today, $thisWeek])
        ->assertCanNotSeeTableRecords([$thisMonth, $previousMonth, $foreign]);

    $component->filterTable('date_period', ['period' => 'MONTH'])
        ->assertCanSeeTableRecords([$today, $thisWeek, $thisMonth])
        ->assertCanNotSeeTableRecords([$previousMonth, $foreign]);

    $component->filterTable('date_period', [
        'period' => 'CUSTOM',
        'from' => '2026-08-31',
        'until' => '2026-09-01',
    ])->assertCanSeeTableRecords([$thisMonth, $previousMonth])
        ->assertCanNotSeeTableRecords([$today, $thisWeek, $foreign]);
});

it('defaults expenses to today', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 12:00:00', 'Asia/Tashkent'));
    [$organization, $store, $owner] = dateFilterContext();
    $today = Expense::factory()->for($organization)->for($store)->for($owner, 'creator')->create([
        'incurred_on' => '2026-09-16',
    ]);
    $yesterday = Expense::factory()->for($organization)->for($store)->for($owner, 'creator')->create([
        'incurred_on' => '2026-09-15',
    ]);

    Livewire::test(ListExpenses::class)
        ->assertSeeInOrder(['Bugun', 'Hafta', 'Oy', 'Oraliq'])
        ->assertCanSeeTableRecords([$today])
        ->assertCanNotSeeTableRecords([$yesterday]);
});

it('defaults shifts to today', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 12:00:00', 'Asia/Tashkent'));
    [$organization, $store, $owner] = dateFilterContext();
    $todayDevice = Device::factory()->for($organization)->for($store)->create();
    $yesterdayDevice = Device::factory()->for($organization)->for($store)->create();
    $today = Shift::factory()->for($organization)->for($store)->for($todayDevice)->for($owner)->create([
        'opened_at' => '2026-09-16 08:00:00',
        'closed_at' => '2026-09-16 10:00:00',
        'status' => ShiftStatus::Closed,
    ]);
    $yesterday = Shift::factory()->for($organization)->for($store)->for($yesterdayDevice)->for($owner)->create([
        'opened_at' => '2026-09-15 08:00:00',
        'closed_at' => '2026-09-15 10:00:00',
        'status' => ShiftStatus::Closed,
    ]);

    Livewire::test(ListShifts::class)
        ->assertSeeInOrder(['Bugun', 'Hafta', 'Oy', 'Oraliq'])
        ->assertCanSeeTableRecords([$today])
        ->assertCanNotSeeTableRecords([$yesterday]);
});
