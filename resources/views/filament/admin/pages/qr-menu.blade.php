<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-lg font-semibold">{{ app(\App\Support\StoreContext::class)->requireCurrent()->name }} menyusi</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">QR kodni skanerlagan mijoz filialning faol mahsulotlari va narxlarini ko‘radi.</p>

            <label class="mt-6 flex items-center gap-3 text-sm font-medium">
                <input type="checkbox" wire:model="enabled" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                QR menyu yoqilgan
            </label>
            <div class="mt-4">
                <x-filament::button wire:click="save">Saqlash</x-filament::button>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-lg font-semibold">Filial havolasi va QR kodi</h2>
            <p class="mt-2 break-all text-sm text-gray-600 dark:text-gray-300">{{ $this->menuUrl() }}</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <div x-data="{ copied: false }">
                    <x-filament::button color="gray" x-on:click="navigator.clipboard.writeText(@js($this->menuUrl())).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                        <span x-text="copied ? 'Havola nusxalandi' : 'Havolani nusxalash'"></span>
                    </x-filament::button>
                </div>
                <x-filament::button wire:click="downloadQrCode">QR kodni yuklab olish</x-filament::button>
            </div>
            <img class="mt-6 h-60 w-60 rounded-lg bg-white p-2" src="{{ $this->qrDataUri() }}" alt="Filial menyusining QR kodi">
            @unless ($enabled)
                <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">Menyu o‘chirilgan. Mijozlar havolani hozir ocholmaydi.</p>
            @endunless
        </div>
    </div>
</x-filament-panels::page>
