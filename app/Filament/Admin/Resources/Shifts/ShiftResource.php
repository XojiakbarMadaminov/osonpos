<?php

namespace App\Filament\Admin\Resources\Shifts;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Filament\Admin\Resources\Shifts\Pages\ListShifts;
use App\Filament\Admin\Resources\Shifts\Pages\ViewShift;
use App\Models\Shift;
use App\Models\Store;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('store.name')->sortable(),
                TextColumn::make('user.name')->label('Cashier')->searchable()->sortable(),
                TextColumn::make('device.name')->label('Device')->sortable(),
                TextColumn::make('opening_cash')->money('UZS', divideBy: 1)->sortable(),
                TextColumn::make('cash_payments_total')->label('Cash sales')->money('UZS', divideBy: 1),
                TextColumn::make('expected_cash')->state(fn (Shift $record): int => $record->expectedCash())->money('UZS', divideBy: 1),
                TextColumn::make('closing_cash')->money('UZS', divideBy: 1)->placeholder('Open'),
                TextColumn::make('cash_difference')->state(fn (Shift $record): ?int => $record->cashDifference())->money('UZS', divideBy: 1)->placeholder('Open'),
                TextColumn::make('opened_at')->dateTime()->sortable(),
                TextColumn::make('closed_at')->dateTime()->placeholder('Open')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ShiftStatus::Open->value => 'Open',
                    ShiftStatus::Closed->value => 'Closed',
                ]),
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->options(fn (): array => Store::query()
                        ->forTenant(app(TenantContext::class)->requireCurrent())
                        ->whereIn('id', app(StoreAccess::class)->accessibleStoreIds(request()->user()))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                Filter::make('opened_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('opened_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('opened_at', '<=', $date))),
            ])
            ->defaultSort('opened_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('status')->badge(),
            TextEntry::make('store.name')->label('Store'),
            TextEntry::make('user.name')->label('Cashier'),
            TextEntry::make('device.name')->label('Device'),
            TextEntry::make('opening_cash')->money('UZS', divideBy: 1),
            TextEntry::make('cash_payments_total')->label('Cash sales')->money('UZS', divideBy: 1),
            TextEntry::make('card_payments_total')->label('Card sales')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Card))->money('UZS', divideBy: 1),
            TextEntry::make('click_payments_total')->label('Click sales')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Click))->money('UZS', divideBy: 1),
            TextEntry::make('payme_payments_total')->label('Payme sales')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Payme))->money('UZS', divideBy: 1),
            TextEntry::make('other_payments_total')->label('Other sales')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Other))->money('UZS', divideBy: 1),
            TextEntry::make('payments_total')->label('All payments')->money('UZS', divideBy: 1),
            TextEntry::make('expected_cash')->state(fn (Shift $record): int => $record->expectedCash())->money('UZS', divideBy: 1),
            TextEntry::make('closing_cash')->money('UZS', divideBy: 1)->placeholder('Open'),
            TextEntry::make('cash_difference')->state(fn (Shift $record): ?int => $record->cashDifference())->money('UZS', divideBy: 1)->placeholder('Open'),
            TextEntry::make('opened_at')->dateTime(),
            TextEntry::make('closed_at')->dateTime()->placeholder('Open'),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $organization = app(TenantContext::class)->requireCurrent();
        $user = request()->user();
        $isSupervisor = app(OrganizationAuthorization::class)->runForUserInTenant(
            $user,
            $organization,
            fn ($tenantUser): bool => $tenantUser->hasAnyRole([
                OrganizationRole::Owner->value,
                OrganizationRole::Manager->value,
            ]),
        );

        return parent::getEloquentQuery()
            ->with(['store', 'user', 'device'])
            ->withSum('payments as payments_total', 'amount')
            ->withSum([
                'payments as cash_payments_total' => fn (Builder $query): Builder => $query
                    ->where('method', PaymentMethod::Cash->value),
            ], 'amount')
            ->forTenant($organization)
            ->whereIn('store_id', app(StoreAccess::class)->accessibleStoreIds($user))
            ->when(! $isSupervisor, fn (Builder $query): Builder => $query->where('user_id', $user->getKey()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'view' => ViewShift::route('/{record}'),
        ];
    }
}
