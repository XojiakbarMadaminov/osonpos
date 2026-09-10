<?php

namespace App\Filament\Admin\Resources\Orders;

use App\Domain\Authorization\StoreAccess;
use App\Filament\Admin\Resources\Orders\Pages\ListOrders;
use App\Filament\Admin\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_number')->searchable()->sortable(),
                TextColumn::make('store.name')->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_status')->badge(),
                TextColumn::make('total')->money('UZS', divideBy: 1)->sortable(),
                TextColumn::make('opened_at')->dateTime()->sortable(),
            ])
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
            TextEntry::make('subtotal')->money('UZS', divideBy: 1),
            TextEntry::make('delivery_fee')->money('UZS', divideBy: 1),
            TextEntry::make('total')->money('UZS', divideBy: 1),
            TextEntry::make('creator.name'),
            TextEntry::make('opened_at')->dateTime(),
            TextEntry::make('closed_at')->dateTime(),
            TextEntry::make('note')->columnSpanFull(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['store', 'creator'])
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->whereIn('store_id', app(StoreAccess::class)->accessibleStoreIds(request()->user()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
