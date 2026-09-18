<?php

namespace App\Filament\Admin\Resources\Orders;

use App\Enums\AdminNavigationGroup;
use App\Enums\DiscountType;
use App\Enums\OrderType;
use App\Filament\Admin\Resources\Orders\Pages\ListOrders;
use App\Filament\Admin\Resources\Orders\Pages\ViewOrder;
use App\Filament\Admin\Support\DatePeriodFilter;
use App\Models\Order;
use App\Support\StoreContext;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'buyurtma';

    protected static ?string $pluralModelLabel = 'buyurtmalar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Sales;

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_number')->searchable()->sortable(),
                TextColumn::make('store.name')->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_status')->badge(),
                TextColumn::make('discount_amount')
                    ->label('Chegirma')
                    ->money('UZS', divideBy: 1, decimalPlaces: 0)
                    ->toggleable(),
                TextColumn::make('total')->money('UZS', divideBy: 1, decimalPlaces: 0)->sortable(),
                TextColumn::make('opened_at')->dateTime()->sortable(),
            ])
            ->filters([
                DatePeriodFilter::make('business_date'),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->defaultSort('opened_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                Group::make()->schema([
                    Section::make('Asosiy ma\'lumotlar')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('display_number')
                                    ->label('Buyurtma raqami')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),
                                TextEntry::make('opened_at')->label('Ochilgan vaqt')->dateTime(),
                                TextEntry::make('type')->label('Turi')->badge(),
                                TextEntry::make('status')->label('Holati')->badge(),
                            ]),
                        ]),

                    Section::make('Mijoz va Filial')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('store.name')
                                    ->label('Filial')
                                    ->icon('heroicon-m-building-storefront'),
                                TextEntry::make('creator.name')
                                    ->label('Kassir')
                                    ->icon('heroicon-m-user'),
                                TextEntry::make('customer_name')
                                    ->label('Mijoz')
                                    ->placeholder('Biriktirilmagan')
                                    ->icon('heroicon-m-users'),
                                TextEntry::make('customer_phone')
                                    ->label('Mijoz telefoni')
                                    ->placeholder('—')
                                    ->icon('heroicon-m-phone'),
                                TextEntry::make('deliveryDetail.address')
                                    ->label('Yetkazish manzili')
                                    ->icon('heroicon-m-map-pin')
                                    ->visible(fn ($record) => $record->type === OrderType::Delivery)
                                    ->columnSpanFull(),
                            ]),
                        ]),
                ])->columnSpan(['default' => 3, 'md' => 2]),

                Group::make()->schema([
                    Section::make('Moliyaviy xulosa')
                        ->schema([
                            TextEntry::make('payment_status')
                                ->label('To\'lov holati')
                                ->badge(),
                            TextEntry::make('subtotal')
                                ->label('Oraliq jami')
                                ->money('UZS', divideBy: 1, decimalPlaces: 0),
                            TextEntry::make('delivery_fee')
                                ->label('Yetkazib berish haqi')
                                ->money('UZS', divideBy: 1, decimalPlaces: 0),
                            TextEntry::make('discount_type')
                                ->label('Chegirma turi')
                                ->formatStateUsing(fn (?DiscountType $state, Order $record): string => match ($state) {
                                    DiscountType::Percentage => "Foiz ({$record->discount_value}%)",
                                    DiscountType::Fixed => 'Summa',
                                    null => '—',
                                })
                                ->visible(fn (Order $record): bool => $record->discount_amount > 0),
                            TextEntry::make('discount_amount')
                                ->label('Chegirma')
                                ->color('success')
                                ->money('UZS', divideBy: 1, decimalPlaces: 0)
                                ->visible(fn (Order $record): bool => $record->discount_amount > 0),
                            TextEntry::make('total')
                                ->label('Jami summa')
                                ->size(TextSize::Large)
                                ->weight(FontWeight::Bold)
                                ->color('success')
                                ->money('UZS', divideBy: 1, decimalPlaces: 0),
                            TextEntry::make('closed_at')->label('Yopilgan vaqt')->dateTime()->placeholder('—'),
                        ]),
                ])->columnSpan(['default' => 3, 'md' => 1]),
            ])->columnSpanFull(),

            Section::make('Mahsulotlar')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('product_name')->label('Nomi')->weight(FontWeight::SemiBold),
                            TextEntry::make('quantity')
                                ->label('Dastlabki miqdor'),
                            TextEntry::make('removed_quantity')
                                ->label('Ayirilgan')
                                ->color('danger')
                                ->state(fn ($record): int => $record->removedQuantity()),
                            TextEntry::make('remaining_quantity')
                                ->label('Qolgan miqdor')
                                ->badge()
                                ->color('info')
                                ->state(fn ($record): int => $record->remainingQuantity()),
                            TextEntry::make('remaining_total')
                                ->label('Jami')
                                ->state(fn ($record): int => $record->remainingTotal())
                                ->money('UZS', divideBy: 1, decimalPlaces: 0)
                                ->weight(FontWeight::Bold),
                        ])
                        ->columns(5)
                        ->columnSpanFull(),
                ]),

            Section::make('Izoh')
                ->schema([
                    TextEntry::make('note')->hiddenLabel(),
                ])
                ->visible(fn ($record) => filled($record->note))
                ->columnSpanFull(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['store', 'creator', 'deliveryDetail', 'items.removals'])
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->forStore(app(StoreContext::class)->requireCurrent());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
