<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Authorization\StoreAccess;
use App\Domain\Reports\SalesReport;
use App\Enums\OrganizationPermission;
use App\Models\Store;
use App\Support\TenantContext;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Reports extends Page
{
    protected string $view = 'filament.admin.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public string $fromDate = '';

    public string $toDate = '';

    public string $storeId = 'all';

    public function mount(): void
    {
        $this->fromDate = now('Asia/Tashkent')->toDateString();
        $this->toDate = now('Asia/Tashkent')->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = request()->user();

        return $user
            && app(TenantContext::class)->current()
            && app(OrganizationAuthorization::class)->allows($user, OrganizationPermission::ReportsView);
    }

    public function stores(): Collection
    {
        return Store::query()
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->whereIn('id', app(StoreAccess::class)->accessibleStoreIds(request()->user()))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function report(): array
    {
        $allowedStoreIds = app(StoreAccess::class)->accessibleStoreIds(request()->user())->map(fn ($id): int => (int) $id);
        $validated = validator([
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'store_id' => $this->storeId,
        ], [
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'store_id' => ['required', Rule::in(['all', ...$allowedStoreIds->map(fn (int $id): string => (string) $id)->all()])],
        ])->validate();

        if (CarbonImmutable::parse($validated['to_date'])->diffInDays(CarbonImmutable::parse($validated['from_date'])) > 366) {
            throw ValidationException::withMessages(['to_date' => 'The report period may not exceed 366 days.']);
        }

        $storeIds = $validated['store_id'] === 'all'
            ? $allowedStoreIds->all()
            : [(int) $validated['store_id']];
        $timezone = Store::query()->whereKey($storeIds[0] ?? null)->value('timezone') ?? 'Asia/Tashkent';
        $from = CarbonImmutable::parse($validated['from_date'], $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($validated['to_date'], $timezone)->endOfDay()->utc();

        return app(SalesReport::class)->generate(
            app(TenantContext::class)->requireCurrent(),
            $from,
            $to,
            $storeIds,
        );
    }
}
