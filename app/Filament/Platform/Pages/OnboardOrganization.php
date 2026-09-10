<?php

namespace App\Filament\Platform\Pages;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\CreateOrganizationData;
use App\Models\Plan;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class OnboardOrganization extends Page
{
    protected string $view = 'filament.platform.pages.onboard-organization';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    public string $name = '';

    public string $organizationSlug = '';

    public ?string $phone = null;

    public string $storeName = '';

    public ?string $storeAddress = null;

    public ?string $storePhone = null;

    public string $timezone = 'Asia/Tashkent';

    public ?int $planId = null;

    public ?int $ownerId = null;

    public string $startsAt = '';

    public string $endsAt = '';

    public function mount(): void
    {
        $this->startsAt = now()->format('Y-m-d\TH:i');
        $this->endsAt = now()->addMonth()->format('Y-m-d\TH:i');
    }

    public function plans(): Collection
    {
        return Plan::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function owners(): Collection
    {
        return User::query()->orderBy('name')->get();
    }

    public function onboard(CreateOrganization $createOrganization): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'organizationSlug' => ['required', 'alpha_dash', 'max:255', 'unique:organizations,slug'],
            'phone' => ['nullable', 'string', 'max:255'],
            'storeName' => ['required', 'string', 'max:255'],
            'storeAddress' => ['nullable', 'string', 'max:255'],
            'storePhone' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'planId' => ['required', 'exists:plans,id'],
            'ownerId' => ['required', 'exists:users,id'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
        ]);

        $organization = $createOrganization->execute(new CreateOrganizationData(
            name: $validated['name'],
            slug: $validated['organizationSlug'],
            phone: $validated['phone'],
            storeName: $validated['storeName'],
            storeAddress: $validated['storeAddress'],
            storePhone: $validated['storePhone'],
            timezone: $validated['timezone'],
            planId: (int) $validated['planId'],
            ownerId: (int) $validated['ownerId'],
            startsAt: CarbonImmutable::parse($validated['startsAt']),
            endsAt: CarbonImmutable::parse($validated['endsAt']),
        ));

        Notification::make()->success()->title("{$organization->name} is ready")->send();
        $this->redirect(static::getUrl());
    }
}
