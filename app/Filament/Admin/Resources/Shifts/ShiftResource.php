<?php

namespace App\Filament\Admin\Resources\Shifts;

use App\Actions\Shifts\CloseShift;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\AdminNavigationGroup;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Filament\Admin\Resources\Shifts\Pages\ListShifts;
use App\Filament\Admin\Resources\Shifts\Pages\ViewShift;
use App\Filament\Admin\Support\DatePeriodFilter;
use App\Models\Shift;
use App\Support\StoreContext;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static ?string $modelLabel = 'smena';

    protected static ?string $pluralModelLabel = 'smenalar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Sales;

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('store.name')->sortable(),
                TextColumn::make('user.name')->label('Kassir')->searchable()->sortable(),
                TextColumn::make('device.name')->label('Qurilma')->sortable(),
                TextColumn::make('opening_cash')->money('UZS', divideBy: 1, decimalPlaces: 0)->sortable(),
                TextColumn::make('cash_payments_total')->label('Naqd savdo')->money('UZS', divideBy: 1, decimalPlaces: 0),
                TextColumn::make('expected_cash')->state(fn (Shift $record): int => $record->expectedCash())->money('UZS', divideBy: 1, decimalPlaces: 0),
                TextColumn::make('closing_cash')->money('UZS', divideBy: 1, decimalPlaces: 0)->placeholder('Ochiq'),
                TextColumn::make('cash_difference')->state(fn (Shift $record): ?int => $record->cashDifference())->money('UZS', divideBy: 1, decimalPlaces: 0)->placeholder('Ochiq'),
                TextColumn::make('opened_at')->dateTime()->sortable(),
                TextColumn::make('closed_at')->dateTime()->placeholder('Ochiq')->sortable(),
            ])
            ->filters([
                DatePeriodFilter::make('opened_at'),
                SelectFilter::make('status')
                    ->label('Holati')
                    ->options([
                        ShiftStatus::Open->value => ShiftStatus::Open->getLabel(),
                        ShiftStatus::Closed->value => ShiftStatus::Closed->getLabel(),
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['sm' => 2, 'lg' => 3])
            ->deferFilters(false)
            ->defaultSort('opened_at', 'desc')
            ->recordActions([
                ViewAction::make()->label('Ko‘rish'),
                self::closeAction(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('status')->badge(),
            TextEntry::make('store.name')->label('Filial'),
            TextEntry::make('user.name')->label('Kassir'),
            TextEntry::make('device.name')->label('Qurilma'),
            TextEntry::make('opening_cash')->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('cash_payments_total')->label('Naqd savdo')->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('card_payments_total')->label('Karta orqali savdo')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Card))->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('click_payments_total')->label('Click orqali savdo')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Click))->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('payme_payments_total')->label('Payme orqali savdo')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Payme))->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('other_payments_total')->label('Boshqa to‘lovlar orqali savdo')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Other))->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('payments_total')->label('Barcha to‘lovlar')->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('expected_cash')->state(fn (Shift $record): int => $record->expectedCash())->money('UZS', divideBy: 1, decimalPlaces: 0),
            TextEntry::make('closing_cash')->money('UZS', divideBy: 1, decimalPlaces: 0)->placeholder('Ochiq'),
            TextEntry::make('cash_difference')->state(fn (Shift $record): ?int => $record->cashDifference())->money('UZS', divideBy: 1, decimalPlaces: 0)->placeholder('Ochiq'),
            TextEntry::make('opened_at')->dateTime(),
            TextEntry::make('closed_at')->dateTime()->placeholder('Ochiq'),
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
            ->forStore(app(StoreContext::class)->requireCurrent())
            ->when(! $isSupervisor, fn (Builder $query): Builder => $query->where('user_id', $user->getKey()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'view' => ViewShift::route('/{record}'),
        ];
    }

    public static function closeAction(): Action
    {
        return Action::make('close_shift')
            ->label('Smenani yopish')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->visible(fn (Shift $record): bool => $record->status === ShiftStatus::Open
                && Gate::allows('closeAsSupervisor', $record))
            ->modalHeading('Smenani yopish')
            ->modalDescription('Kassadagi haqiqiy naqd pulni kiriting. Ushbu amal smenani yakunlaydi.')
            ->modalSubmitActionLabel('Smenani yopish')
            ->schema([
                TextInput::make('closing_cash')
                    ->label('Yopilishdagi naqd pul')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->suffix('UZS')
                    ->required(),
            ])
            ->action(function (Shift $record, array $data): void {
                Gate::authorize('closeAsSupervisor', $record);
                try {
                    app(CloseShift::class)->executeAsSupervisor(
                        $record,
                        request()->user(),
                        (int) $data['closing_cash'],
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Smena yopilmadi')
                        ->body(collect($exception->errors())->flatten()->first())
                        ->persistent()
                        ->send();

                    return;
                }
                $record->refresh();

                Notification::make()
                    ->success()
                    ->title('Smena yopildi')
                    ->send();
            });
    }
}
