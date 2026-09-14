<x-filament-panels::page>
    <form wire:submit="onboard" class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Tashkilot">
            <div class="space-y-5">
                <x-filament-forms::field-wrapper label="Tashkilot nomi" state-path="name" required>
                    <x-filament::input.wrapper><x-filament::input id="name" wire:model="name" /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
                <x-filament-forms::field-wrapper label="Manzil identifikatori" state-path="organizationSlug" required>
                    <x-filament::input.wrapper><x-filament::input id="organizationSlug" wire:model="organizationSlug" placeholder="organization-slug" /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
                <x-filament-forms::field-wrapper label="Telefon" state-path="phone">
                    <x-filament::input.wrapper><x-filament::input id="phone" wire:model="phone" placeholder="+998..." /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
                <x-filament-forms::field-wrapper label="Egani sozlash" state-path="ownerMode" required>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="ownerMode" wire:model.live="ownerMode">
                            <option value="new">Yangi ega yaratish</option>
                            <option value="existing">Mavjud foydalanuvchini tanlash</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                @if ($ownerMode === 'existing')
                    <x-filament-forms::field-wrapper label="Mavjud foydalanuvchi" state-path="ownerId" required>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="ownerId" wire:model="ownerId">
                                <option value="">Egani tanlang</option>
                                @foreach ($this->owners() as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }} — {{ $owner->email }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </x-filament-forms::field-wrapper>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section heading="Birinchi filial">
            <div class="space-y-5">
                <x-filament-forms::field-wrapper label="Filial nomi" state-path="storeName" required>
                    <x-filament::input.wrapper><x-filament::input id="storeName" wire:model="storeName" /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
                <x-filament-forms::field-wrapper label="Manzil" state-path="storeAddress">
                    <x-filament::input.wrapper><x-filament::input id="storeAddress" wire:model="storeAddress" /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
                <x-filament-forms::field-wrapper label="Telefon" state-path="storePhone">
                    <x-filament::input.wrapper><x-filament::input id="storePhone" wire:model="storePhone" placeholder="+998..." /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
                <x-filament-forms::field-wrapper label="Vaqt mintaqasi" state-path="timezone" required>
                    <x-filament::input.wrapper><x-filament::input id="timezone" wire:model="timezone" /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
            </div>
        </x-filament::section>

        @if ($ownerMode === 'new')
            <x-filament::section heading="Tashkilot egasi" description="Bu foydalanuvchiga Ega roli beriladi va u darhol /admin manziliga kira oladi." class="lg:col-span-2">
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    <x-filament-forms::field-wrapper label="To‘liq ism" state-path="ownerName" required>
                        <x-filament::input.wrapper><x-filament::input id="ownerName" wire:model="ownerName" autocomplete="name" /></x-filament::input.wrapper>
                    </x-filament-forms::field-wrapper>
                    <x-filament-forms::field-wrapper label="Elektron pochta" state-path="ownerEmail" required>
                        <x-filament::input.wrapper><x-filament::input id="ownerEmail" type="email" wire:model="ownerEmail" autocomplete="email" /></x-filament::input.wrapper>
                    </x-filament-forms::field-wrapper>
                    <x-filament-forms::field-wrapper label="Parol" state-path="ownerPassword" required>
                        <x-filament::input.wrapper><x-filament::input id="ownerPassword" type="password" wire:model="ownerPassword" autocomplete="new-password" /></x-filament::input.wrapper>
                    </x-filament-forms::field-wrapper>
                    <x-filament-forms::field-wrapper label="Parolni tasdiqlash" state-path="ownerPassword_confirmation" required>
                        <x-filament::input.wrapper><x-filament::input id="ownerPassword_confirmation" type="password" wire:model="ownerPassword_confirmation" autocomplete="new-password" /></x-filament::input.wrapper>
                    </x-filament-forms::field-wrapper>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section heading="Obuna" class="lg:col-span-2">
            <div class="grid gap-5 md:grid-cols-3">
                <x-filament-forms::field-wrapper label="Tarif" state-path="planId" required>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="planId" wire:model="planId">
                            <option value="">Tarifni tanlang</option>
                            @foreach ($this->plans() as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
                <x-filament-forms::field-wrapper label="Boshlanish vaqti" state-path="startsAt" required>
                    <x-filament::input.wrapper><x-filament::input id="startsAt" type="datetime-local" wire:model="startsAt" /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
                <x-filament-forms::field-wrapper label="Tugash vaqti" state-path="endsAt" required>
                    <x-filament::input.wrapper><x-filament::input id="endsAt" type="datetime-local" wire:model="endsAt" /></x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>
            </div>
        </x-filament::section>

        <div class="lg:col-span-2">
            <x-filament::button type="submit">Tashkilot yaratish</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
