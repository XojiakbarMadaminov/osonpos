<x-filament-widgets::widget>
    <x-filament::section
        heading="Tashkilot paneliga kirish"
        description="Boshqariladigan tashkilotni tanlang. Siz uning admin paneliga to‘liq boshqaruv huquqi bilan kirasiz."
    >
        <form method="POST" action="{{ route('platform.organization-access.enter') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
            @csrf

            <div class="w-full sm:max-w-xl">
                <label for="platform-organization" class="mb-2 block text-sm font-medium text-gray-950 dark:text-white">
                    Tashkilot
                </label>
                <select
                    id="platform-organization"
                    name="organization_id"
                    required
                    class="fi-select-input block w-full rounded-lg border-gray-300 bg-white py-2 pe-8 ps-3 text-sm text-gray-950 shadow-sm outline-none transition duration-75 focus:border-primary-600 focus:ring-1 focus:ring-primary-600 disabled:pointer-events-none disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500"
                >
                    <option value="">Tashkilotni tanlang</option>
                    @foreach ($this->organizations() as $organization)
                        <option value="{{ $organization->getKey() }}" @selected((int) old('organization_id') === $organization->getKey())>
                            {{ $organization->name }} — {{ $organization->status->getLabel() }}
                        </option>
                    @endforeach
                </select>

                @error('organization_id')
                    <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </div>

            <x-filament::button type="submit">
                Admin panelga kirish
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>
