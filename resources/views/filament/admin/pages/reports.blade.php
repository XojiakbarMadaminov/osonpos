<x-filament-panels::page>
    @php($formatNumber = fn (int $value): string => number_format($value, 0, '.', ' '))
    <div class="space-y-6">
        <x-filament::section heading="Filtrlar">
            <div class="grid gap-4 md:grid-cols-3">
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="fromDate" /></x-filament::input.wrapper>
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="toDate" /></x-filament::input.wrapper>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="storeId">
                        <option value="all">Barcha ruxsat etilgan filiallar</option>
                        @foreach ($this->stores() as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-filament::section>

        @php($report = $this->report())
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section heading="Tushum"><div class="text-2xl font-semibold">{{ $formatNumber($report['revenue']) }} UZS</div></x-filament::section>
            <x-filament::section heading="Taxminiy yalpi foyda"><div class="text-2xl font-semibold">{{ $formatNumber($report['estimated_gross_profit']) }} UZS</div></x-filament::section>
            <x-filament::section heading="Chiqimlar"><div class="text-2xl font-semibold">{{ $formatNumber($report['expense_total']) }} UZS</div></x-filament::section>
            <x-filament::section heading="Buyurtmalar"><div class="text-2xl font-semibold">{{ $formatNumber($report['order_count']) }}</div></x-filament::section>
            <x-filament::section heading="O‘rtacha chek"><div class="text-2xl font-semibold">{{ $formatNumber($report['average_check']) }} UZS</div></x-filament::section>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-filament::section heading="To‘lov turlari bo‘yicha">
                <div class="space-y-2">
                    @forelse ($report['payment_breakdown'] as $row)
                        <div class="flex justify-between"><span>{{ $row['label'] }}</span><strong>{{ $formatNumber($row['total']) }} UZS</strong></div>
                    @empty
                        <p>Bu davrda yakunlangan savdolar yo‘q.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Buyurtma turlari bo‘yicha">
                <div class="space-y-2">
                    @forelse ($report['order_type_breakdown'] as $row)
                        <div class="flex justify-between"><span>{{ $row['label'] }}</span><strong>{{ $formatNumber($row['total']) }}</strong></div>
                    @empty
                        <p>Bu davrda yakunlangan buyurtmalar yo‘q.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Chiqim turlari bo‘yicha">
                <div class="space-y-2">
                    @forelse ($report['expense_breakdown'] as $row)
                        <div class="flex justify-between gap-4"><span>{{ $row['label'] }}</span><strong class="text-right">{{ $formatNumber($row['total']) }} UZS</strong></div>
                    @empty
                        <p>Bu davrda faol chiqimlar yo‘q.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <x-filament::section heading="Eng ko‘p sotilgan mahsulotlar">
            <div class="space-y-2">
                @forelse ($report['top_products'] as $row)
                    <div class="grid grid-cols-3 gap-4">
                        <span>{{ $row['name'] }}</span>
                        <span class="text-right">{{ $formatNumber($row['quantity']) }} ta sotildi</span>
                        <strong class="text-right">{{ $formatNumber($row['revenue']) }} UZS</strong>
                    </div>
                @empty
                    <p>Bu davrda yakunlangan savdolar yo‘q.</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
