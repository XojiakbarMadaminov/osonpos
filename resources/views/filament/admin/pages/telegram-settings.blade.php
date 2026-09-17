<x-filament-panels::page>
    <form wire:submit="save" class="max-w-2xl space-y-6">
        <x-filament::section
            heading="To‘lov xabarlari guruhi"
            description="SaaS Telegram botini guruhga qo‘shing va shu guruhning ID raqamini kiriting. Har bir muvaffaqiyatli to‘lovdan keyin xabar ushbu guruhga yuboriladi."
        >
            <x-filament-forms::field-wrapper label="Telegram guruh ID si" state-path="groupChatId" required>
                <x-filament::input.wrapper>
                    <x-filament::input
                        id="groupChatId"
                        wire:model="groupChatId"
                        placeholder="-1001234567890"
                        inputmode="numeric"
                    />
                </x-filament::input.wrapper>
            </x-filament-forms::field-wrapper>
        </x-filament::section>

        <x-filament::button type="submit">Saqlash</x-filament::button>
    </form>
</x-filament-panels::page>
