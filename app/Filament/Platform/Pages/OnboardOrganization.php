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

    protected static string|UnitEnum|null $navigationGroup = 'Mijozlar';

    protected static ?string $navigationLabel = 'Tashkilot qo‘shish';

    protected static ?string $title = 'Tashkilot qo‘shish';

    public string $name = '';

    public string $organizationSlug = '';

    public ?string $phone = null;

    public string $storeName = '';

    public ?string $storeAddress = null;

    public ?string $storePhone = null;

    public string $timezone = 'Asia/Tashkent';

    public ?int $planId = null;

    public ?int $ownerId = null;

    public string $ownerMode = 'new';

    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $ownerPassword = '';

    public string $ownerPassword_confirmation = '';

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
            'ownerMode' => ['required', 'in:new,existing'],
            'ownerId' => ['nullable', 'required_if:ownerMode,existing', 'exists:users,id'],
            'ownerName' => ['nullable', 'required_if:ownerMode,new', 'string', 'max:255'],
            'ownerEmail' => ['nullable', 'required_if:ownerMode,new', 'email', 'max:255', 'unique:users,email'],
            'ownerPassword' => ['nullable', 'required_if:ownerMode,new', 'string', 'min:8', 'confirmed'],
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
            ownerId: $validated['ownerMode'] === 'existing' ? (int) $validated['ownerId'] : null,
            startsAt: CarbonImmutable::parse($validated['startsAt']),
            endsAt: CarbonImmutable::parse($validated['endsAt']),
            ownerName: $validated['ownerMode'] === 'new' ? $validated['ownerName'] : null,
            ownerEmail: $validated['ownerMode'] === 'new' ? $validated['ownerEmail'] : null,
            ownerPassword: $validated['ownerMode'] === 'new' ? $validated['ownerPassword'] : null,
        ));

        Notification::make()->success()->title("{$organization->name} tayyor")->send();
        $this->redirect(static::getUrl());
    }
}
