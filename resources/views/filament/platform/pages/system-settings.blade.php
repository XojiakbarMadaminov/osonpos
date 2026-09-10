<x-filament-panels::page>
    <x-filament::section heading="Support details" description="Non-sensitive runtime information for basic support diagnostics.">
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
