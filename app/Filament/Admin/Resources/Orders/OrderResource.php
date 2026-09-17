<?php

namespace App\Filament\Admin\Resources\Orders;

use App\Enums\AdminNavigationGroup;
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
use Filament\Schemas\Schema;
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
            \Filament\Schemas\Components\Grid::make(3)->schema([
                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Asosiy ma\'lumotlar')
                        ->schema([
                            \Filament\Schemas\Components\Grid::make(2)->schema([
                                TextEntry::make('display_number')
                                    ->label('Buyurtma raqami')
                                    ->size(\Filament\Support\Enums\TextSize::Large)
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                    ->copyable(),
                                TextEntry::make('opened_at')->label('Ochilgan vaqt')->dateTime(),
                                TextEntry::make('type')->label('Turi')->badge(),
                                TextEntry::make('status')->label('Holati')->badge(),
                            ]),
                        ]),

                    \Filament\Schemas\Components\Section::make('Mijoz va Filial')
                        ->schema([
                            \Filament\Schemas\Components\Grid::make(2)->schema([
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
                                    ->visible(fn ($record) => $record->type === \App\Enums\OrderType::Delivery)
                                    ->columnSpanFull(),
                            ]),
                        ]),
                ])->columnSpan(['default' => 3, 'md' => 2]),

                \Filament\Schemas\Components\Group::make()->schema([
                    \Filament\Schemas\Components\Section::make('Moliyaviy xulosa')
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
                            TextEntry::make('total')
                                ->label('Jami summa')
                                ->size(\Filament\Support\Enums\TextSize::Large)
                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                ->color('success')
                                ->money('UZS', divideBy: 1, decimalPlaces: 0),
                            TextEntry::make('closed_at')->label('Yopilgan vaqt')->dateTime()->placeholder('—'),
                        ]),
                ])->columnSpan(['default' => 3, 'md' => 1]),
            ])->columnSpanFull(),

            \Filament\Schemas\Components\Section::make('Mahsulotlar')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('product_name')->label('Nomi')->weight(\Filament\Support\Enums\FontWeight::SemiBold),
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
                                ->weight(\Filament\Support\Enums\FontWeight::Bold),
                        ])
                        ->columns(5)
                        ->columnSpanFull(),
                ]),

            \Filament\Schemas\Components\Section::make('Izoh')
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
