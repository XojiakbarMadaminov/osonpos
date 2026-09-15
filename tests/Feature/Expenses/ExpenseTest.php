<?php

use App\Actions\Expenses\CancelExpense;
use App\Actions\Expenses\CreateExpense;
use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Filament\Admin\Resources\Expenses\Pages\CreateExpense as CreateExpensePage;
use App\Models\Expense;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function expenseContext(OrganizationRole $role = OrganizationRole::Owner): array
{
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $user,
        $organization,
        fn (User $tenantUser) => $tenantUser->assignRole($role->value),
    );
    $tenantContext = app(TenantContext::class);
    $tenantContext->resolveFor($user, $organization->id);
    app(StoreContext::class)->resolveFor($user, $tenantContext, $store->id);

    return [$organization, $store, $user, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
    ]];
}

it('creates a store-scoped expense with integer UZS amount', function () {
    [$organization, $store, $owner] = expenseContext();

    $expense = app(CreateExpense::class)->execute(
        $owner,
        $store->id,
        ExpenseType::ProductCost,
        325000,
        'Haftalik mahsulot xaridi',
        CarbonImmutable::parse('2026-09-14'),
    );

    expect($expense->organization_id)->toBe($organization->id)
        ->and($expense->store_id)->toBe($store->id)
        ->and($expense->amount)->toBe(325000)
        ->and($expense->type)->toBe(ExpenseType::ProductCost)
        ->and($expense->status)->toBe(ExpenseStatus::Active)
        ->and($expense->creator->is($owner))->toBeTrue();
});

it('allows creating an expense without a description', function () {
    [$organization, $store, $owner] = expenseContext();

    $expense = app(CreateExpense::class)->execute(
        $owner,
        $store->id,
        ExpenseType::Rent,
        100000,
        null,
        CarbonImmutable::today(),
    );

    expect($expense->organization_id)->toBe($organization->id)
        ->and($expense->description)->toBeNull();
});

it('creates expenses for the active store without a store selector', function () {
    [, $store, $owner, $session] = expenseContext();

    $this->actingAs($owner)->withSession($session);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateExpensePage::class)
        ->assertFormFieldDoesNotExist('store_id')
        ->fillForm([
            'type' => ExpenseType::Other->value,
            'amount' => 25000,
            'incurred_on' => today()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Expense::query()->sole()->store_id)->toBe($store->id);
});

it('validates amount and tenant store ownership', function () {
    [, $store, $owner] = expenseContext();

    expect(fn () => app(CreateExpense::class)->execute(
        $owner,
        $store->id,
        ExpenseType::Other,
        0,
        '',
        CarbonImmutable::today(),
    ))->toThrow(ValidationException::class);

    $foreignStore = Store::factory()->create();
    app(CreateExpense::class)->execute(
        $owner,
        $foreignStore->id,
        ExpenseType::Rent,
        100000,
        'Begona filial',
        CarbonImmutable::today(),
    );
})->throws(ModelNotFoundException::class);

it('cancels rather than deleting expense history', function () {
    [$organization, $store, $owner] = expenseContext();
    $expense = Expense::factory()->for($organization)->for($store)->for($owner, 'creator')->create();

    app(CancelExpense::class)->execute($expense, $owner, 'Summa xato kiritilgan');

    expect($expense->refresh()->status)->toBe(ExpenseStatus::Cancelled)
        ->and($expense->cancelled_at)->not->toBeNull()
        ->and($expense->canceller->is($owner))->toBeTrue()
        ->and($expense->cancellation_reason)->toBe('Summa xato kiritilgan')
        ->and(fn () => $expense->delete())->toThrow(ValidationException::class);
});

it('calculates active expenses only', function () {
    [$organization, $store, $owner] = expenseContext();
    Expense::factory()->for($organization)->for($store)->for($owner, 'creator')->create(['amount' => 100000]);
    Expense::factory()->for($organization)->for($store)->for($owner, 'creator')->create([
        'amount' => 60000,
        'status' => ExpenseStatus::Cancelled,
        'cancelled_by' => $owner->id,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Bekor qilingan',
    ]);

    expect((int) Expense::query()->forTenant($organization)->forStore($store)->active()->sum('amount'))->toBe(100000);
});

it('provides Uzbek labels for every default expense type', function () {
    expect(collect(ExpenseType::cases())->map->getLabel()->all())->toBe([
        'Mahsulotlar xarajati',
        'Ijara',
        'Oylik maosh',
        'Boshqa',
    ]);
});

it('allows owner and denies manager by default in the admin panel', function () {
    [$organization, $store, $owner, $ownerSession] = expenseContext();
    $expense = Expense::factory()->for($organization)->for($store)->for($owner, 'creator')->create();

    $this->actingAs($owner)->withSession($ownerSession)
        ->get('/admin/expenses')
        ->assertOk()
        ->assertSee('Chiqimlar')
        ->assertSee('Mahsulotlar xarajati');
    $this->actingAs($owner)->withSession($ownerSession)
        ->get("/admin/expenses/{$expense->id}")
        ->assertOk();

    [, , $manager, $managerSession] = expenseContext(OrganizationRole::Manager);
    $this->actingAs($manager)->withSession($managerSession)
        ->get('/admin/expenses')
        ->assertForbidden();
});

it('enforces tenant and store isolation for explicitly authorized users', function () {
    [$organization, $store, $manager, $session] = expenseContext(OrganizationRole::Manager);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $manager,
        $organization,
        fn (User $tenantUser) => $tenantUser->givePermissionTo(OrganizationPermission::ExpensesView->value),
    );
    $ownExpense = Expense::factory()->for($organization)->for($store)->for($manager, 'creator')->create();
    $blockedStore = Store::factory()->for($organization)->create();
    $blockedExpense = Expense::factory()->for($organization)->for($blockedStore)->for($manager, 'creator')->create();
    $foreignExpense = Expense::factory()->create();

    $this->actingAs($manager)->withSession($session)
        ->get("/admin/expenses/{$ownExpense->id}")
        ->assertOk();
    $this->actingAs($manager)->withSession($session)
        ->get("/admin/expenses/{$blockedExpense->id}")
        ->assertNotFound();
    $this->actingAs($manager)->withSession($session)
        ->get("/admin/expenses/{$foreignExpense->id}")
        ->assertNotFound();
});
