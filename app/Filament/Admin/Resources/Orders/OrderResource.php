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
            TextEntry::make('display_number'),
            TextEntry::make('store.name'),
            TextEntry::make('type')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('payment_status')->badge(),
            TextEntry::make('customer_name')->label('Mijoz')->placeholder('Biriktirilmagan'),
            TextEntry::make('customer_phone')->label('Mijoz telefoni')->placeholder('—'),
            TextEntry::make('subtotal')->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('delivery_fee')->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('total')->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('creator.name'),
            TextEntry::make('opened_at')->dateTime(),
            TextEntry::make('closed_at')->dateTime(),
            TextEntry::make('note')->columnSpanFull(),
            RepeatableEntry::make('items')
                ->label('Mahsulotlar')
                ->schema([
                    TextEntry::make('product_name')->label('Mahsulot'),
                    TextEntry::make('quantity')
                        ->label('Dastlabki miqdor'),
                    TextEntry::make('removed_quantity')
                        ->label('Ayirilgan')
                        ->state(fn ($record): int => $record->removedQuantity()),
                    TextEntry::make('remaining_quantity')
                        ->label('Qolgan miqdor')
                        ->state(fn ($record): int => $record->remainingQuantity()),
                    TextEntry::make('remaining_total')
                        ->label('Qolgan summa')
                        ->state(fn ($record): int => $record->remainingTotal())
                        ->money('UZS', divideBy: 1, decimalPlaces: 0),
                ])
                ->columns(5)
                ->columnSpanFull(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['store', 'creator', 'items.removals'])
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
