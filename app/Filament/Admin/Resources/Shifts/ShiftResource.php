<?php

namespace App\Filament\Admin\Resources\Shifts;

use App\Actions\Shifts\CloseShift;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Platform\PlatformOrganizationAccess;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
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
                TextColumn::make('store.name')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')->label('Kassir')->searchable()->sortable(),
                TextColumn::make('opening_cash')->label('Kassa (Ochilish)')->money('UZS', divideBy: 1, decimalPlaces: 0)->sortable(),
                TextColumn::make('cash_payments_total')->label('Naqd savdo')->money('UZS', divideBy: 1, decimalPlaces: 0),
                TextColumn::make('card_payments_total')->label('Karta savdo')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Card))->money('UZS', divideBy: 1, decimalPlaces: 0),
                TextColumn::make('payments_total')->label('Umumiy savdo')->money('UZS', divideBy: 1, decimalPlaces: 0),
                TextColumn::make('expected_total')->label('Kutilayotgan umumiy')->state(fn (Shift $record): int => $record->opening_cash + $record->paymentTotal(PaymentMethod::Cash) + $record->paymentTotal(PaymentMethod::Card))->money('UZS', divideBy: 1, decimalPlaces: 0),
                TextColumn::make('expected_cash')->label('Kutil. naqd')->state(fn (Shift $record): int => $record->expectedCash())->money('UZS', divideBy: 1, decimalPlaces: 0)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closing_cash')->label('Kassa (Yopilish)')->money('UZS', divideBy: 1, decimalPlaces: 0)->placeholder('Ochiq'),
                TextColumn::make('cash_difference')->label('Naqd farq')->state(fn (Shift $record): ?int => $record->cashDifference())->money('UZS', divideBy: 1, decimalPlaces: 0)->placeholder('Ochiq')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('opened_at')->label('Ochilgan')->dateTime()->sortable(),
                TextColumn::make('closed_at')->label('Yopilgan')->dateTime()->placeholder('Ochiq')->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            Grid::make(3)->schema([
                Group::make()->schema([
                    Section::make('Asosiy ma\'lumotlar')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('status')->label('Holati')->badge(),
                                TextEntry::make('store.name')->label('Filial')->icon('heroicon-m-building-storefront'),
                                TextEntry::make('user.name')->label('Kassir')->icon('heroicon-m-user'),
                                TextEntry::make('device.name')->label('Qurilma')->icon('heroicon-m-computer-desktop'),
                                TextEntry::make('opened_at')->label('Ochilgan vaqt')->dateTime(),
                                TextEntry::make('closed_at')->label('Yopilgan vaqt')->dateTime()->placeholder('Ochiq'),
                            ]),
                        ]),
                    Section::make('Savdo turlari bo\'yicha')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('cash_payments_total')->label('Naqd')->state(fn (Shift $record): int => $record->cashPaymentsTotal())->money('UZS', divideBy: 1, decimalPlaces: 0),
                                TextEntry::make('card_payments_total')->label('Karta (Terminal)')->state(fn (Shift $record): int => $record->paymentTotal(PaymentMethod::Card))->money('UZS', divideBy: 1, decimalPlaces: 0),
                                TextEntry::make('payments_total')->label('Barcha to‘lovlar')->money('UZS', divideBy: 1, decimalPlaces: 0)
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->columnSpanFull(),
                            ]),
                        ]),
                ])->columnSpan(['default' => 3, 'md' => 2]),

                Group::make()->schema([
                    Section::make('Smena moliya xulosasi')
                        ->schema([
                            TextEntry::make('opening_cash')->label('Ochilishdagi naqd pul')->money('UZS', divideBy: 1, decimalPlaces: 0),
                            TextEntry::make('payments_total')->label('Umumiy savdo (+)')
                                ->color('success')
                                ->money('UZS', divideBy: 1, decimalPlaces: 0),
                            TextEntry::make('expected_total')->label('Kutilayotgan umumiy summa')
                                ->state(fn (Shift $record): int => $record->opening_cash + $record->paymentTotal(PaymentMethod::Cash) + $record->paymentTotal(PaymentMethod::Card))
                                ->size(TextSize::Large)
                                ->weight(FontWeight::Bold)
                                ->money('UZS', divideBy: 1, decimalPlaces: 0),
                            TextEntry::make('closing_cash')->label('Yopilishdagi haqiqiy naqd pul')
                                ->money('UZS', divideBy: 1, decimalPlaces: 0)
                                ->placeholder('Smena ochiq'),
                            TextEntry::make('cash_difference')->label('Naqd pul tafovuti')
                                ->state(fn (Shift $record): ?int => $record->cashDifference())
                                ->color(fn ($state) => $state === null ? null : ($state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray')))
                                ->money('UZS', divideBy: 1, decimalPlaces: 0)
                                ->placeholder('Smena ochiq'),
                        ]),
                ])->columnSpan(['default' => 3, 'md' => 1]),
            ])->columnSpanFull(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $organization = app(TenantContext::class)->requireCurrent();
        $user = request()->user();
        $isSupervisor = app(PlatformOrganizationAccess::class)->isActiveFor($user, $organization)
            || app(OrganizationAuthorization::class)->runForUserInTenant(
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
