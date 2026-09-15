<?php

use App\Actions\Organizations\AssignOrganizationOwner;
use App\Enums\ShiftStatus;
use App\Filament\Admin\Resources\Devices\Pages\ListDevices;
use App\Filament\Admin\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Admin\Resources\Orders\Pages\ListOrders;
use App\Filament\Admin\Resources\Printers\Pages\ListPrinters;
use App\Filament\Admin\Resources\Shifts\Pages\ListShifts;
use App\Filament\Admin\Resources\Tables\Pages\ListTables;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Printer;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Support\Number;
use Livewire\Livewire;

it('scopes all store-owned admin tables to the active store without store filters', function () {
    $organization = Organization::factory()->create();
    $activeStore = Store::factory()->for($organization)->create(['name' => 'Faol filial']);
    $otherStore = Store::factory()->for($organization)->create(['name' => 'Boshqa filial']);
    $owner = User::factory()->create();
    app(AssignOrganizationOwner::class)->execute($organization, $owner);
    Subscription::factory()->for($organization)->create();

    $activeDevice = Device::factory()->for($organization)->for($activeStore)->create();
    $otherDevice = Device::factory()->for($organization)->for($otherStore)->create();
    $activeRecords = [
        ListOrders::class => Order::factory()->for($organization)->for($activeStore)->for($owner, 'creator')->create([
            'subtotal' => 40000,
            'total' => 40000,
        ]),
        ListExpenses::class => Expense::factory()->for($organization)->for($activeStore)->for($owner, 'creator')->create(),
        ListShifts::class => Shift::factory()->for($organization)->for($activeStore)->for($activeDevice)->for($owner)->create([
            'status' => ShiftStatus::Closed,
            'closing_cash' => 0,
            'closed_at' => now(),
        ]),
        ListTables::class => Table::factory()->for($organization)->for($activeStore)->create(),
        ListDevices::class => $activeDevice,
        ListPrinters::class => Printer::factory()->for($organization)->for($activeStore)->create(),
    ];
    $otherRecords = [
        ListOrders::class => Order::factory()->for($organization)->for($otherStore)->for($owner, 'creator')->create(),
        ListExpenses::class => Expense::factory()->for($organization)->for($otherStore)->for($owner, 'creator')->create(),
        ListShifts::class => Shift::factory()->for($organization)->for($otherStore)->for($otherDevice)->for($owner)->create([
            'status' => ShiftStatus::Closed,
            'closing_cash' => 0,
            'closed_at' => now(),
        ]),
        ListTables::class => Table::factory()->for($organization)->for($otherStore)->create(),
        ListDevices::class => $otherDevice,
        ListPrinters::class => Printer::factory()->for($organization)->for($otherStore)->create(),
    ];

    $tenantContext = app(TenantContext::class);
    $tenantContext->resolveFor($owner, $organization->id);
    app(StoreContext::class)->resolveFor($owner, $tenantContext, $activeStore->id);
    $this->actingAs($owner)->withSession([
        'current_organization_id' => $organization->id,
        'current_store_id' => $activeStore->id,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    foreach ($activeRecords as $page => $activeRecord) {
        $component = Livewire::test($page)
            ->assertCanSeeTableRecords([$activeRecord])
            ->assertCanNotSeeTableRecords([$otherRecords[$page]]);

        expect($component->instance()->getTable()->getFilter('store_id'))->toBeNull();
    }

    Livewire::test(ListOrders::class)
        ->assertSee(Number::currency(40000, 'UZS', 'uz', 0))
        ->assertDontSee(',00');

    app(StoreContext::class)->resolveFor($owner, $tenantContext, $otherStore->id);
    session()->put('current_store_id', $otherStore->id);

    foreach ($otherRecords as $page => $otherRecord) {
        Livewire::test($page)
            ->assertCanSeeTableRecords([$otherRecord])
            ->assertCanNotSeeTableRecords([$activeRecords[$page]]);
    }
});
