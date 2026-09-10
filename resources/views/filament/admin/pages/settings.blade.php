<x-filament-panels::page>
    <form wire:submit="saveContext" class="max-w-2xl space-y-6">
        <x-filament::section heading="Current workspace" description="Choose an organization and one of your assigned stores.">
            <div class="space-y-4">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="organizationId">
                        <option value="">Select organization</option>
                        @foreach ($this->organizations() as $organization)
                            <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="storeId">
                        <option value="">Select store</option>
                        @foreach ($this->stores() as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-filament::section>

        <x-filament::button type="submit">Save workspace</x-filament::button>
    </form>
</x-filament-panels::page>
