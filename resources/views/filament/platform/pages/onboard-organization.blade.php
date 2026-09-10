<x-filament-panels::page>
    <form wire:submit="onboard" class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Organization">
            <div class="space-y-4">
                <x-filament::input.wrapper><x-filament::input wire:model="name" placeholder="Organization name" /></x-filament::input.wrapper>
                <x-filament::input.wrapper><x-filament::input wire:model="organizationSlug" placeholder="organization-slug" /></x-filament::input.wrapper>
                <x-filament::input.wrapper><x-filament::input wire:model="phone" placeholder="Phone" /></x-filament::input.wrapper>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="ownerId">
                        <option value="">Select owner</option>
                        @foreach ($this->owners() as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }} — {{ $owner->email }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-filament::section>

        <x-filament::section heading="First store">
            <div class="space-y-4">
                <x-filament::input.wrapper><x-filament::input wire:model="storeName" placeholder="Store name" /></x-filament::input.wrapper>
                <x-filament::input.wrapper><x-filament::input wire:model="storeAddress" placeholder="Address" /></x-filament::input.wrapper>
                <x-filament::input.wrapper><x-filament::input wire:model="storePhone" placeholder="Phone" /></x-filament::input.wrapper>
                <x-filament::input.wrapper><x-filament::input wire:model="timezone" placeholder="Timezone" /></x-filament::input.wrapper>
            </div>
        </x-filament::section>

        <x-filament::section heading="Subscription" class="lg:col-span-2">
            <div class="grid gap-4 md:grid-cols-3">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="planId">
                        <option value="">Select plan</option>
                        @foreach ($this->plans() as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <x-filament::input.wrapper><x-filament::input type="datetime-local" wire:model="startsAt" /></x-filament::input.wrapper>
                <x-filament::input.wrapper><x-filament::input type="datetime-local" wire:model="endsAt" /></x-filament::input.wrapper>
            </div>
        </x-filament::section>

        <div class="lg:col-span-2">
            <x-filament::button type="submit">Create organization</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
