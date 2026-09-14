<x-filament-panels::page>
    <x-filament::section heading="Texnik ma’lumotlar" description="Texnik yordam uchun maxfiy bo‘lmagan tizim ma’lumotlari.">
        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach ($this->supportDetails() as $label => $value)
                <div>
                    <dt class="text-sm text-gray-500">{{ $label }}</dt>
                    <dd class="font-medium">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>
</x-filament-panels::page>
