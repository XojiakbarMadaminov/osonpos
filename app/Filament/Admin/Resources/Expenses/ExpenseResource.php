<?php

namespace App\Filament\Admin\Resources\Expenses;

use App\Actions\Expenses\CancelExpense;
use App\Enums\AdminNavigationGroup;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Filament\Admin\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Admin\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Admin\Resources\Expenses\Pages\ViewExpense;
use App\Filament\Admin\Support\DatePeriodFilter;
use App\Models\Expense;
use App\Support\StoreContext;
use App\Support\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $modelLabel = 'chiqim';

    protected static ?string $pluralModelLabel = 'chiqimlar';

    protected static ?string $navigationLabel = 'Chiqimlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Sales;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Chiqim turi')
                ->options(collect(ExpenseType::cases())->mapWithKeys(
                    fn (ExpenseType $type): array => [$type->value => $type->getLabel()],
                )->all())
                ->required(),
            TextInput::make('amount')
                ->label('Summa')
                ->integer()
                ->minValue(1)
                ->suffix('UZS')
                ->required(),
            DatePicker::make('incurred_on')
                ->label('Chiqim sanasi')
                ->default(today())
                ->maxDate(today())
                ->required(),
            Textarea::make('description')
                ->label('Nima uchun sarflandi?')
                ->placeholder('Chiqim sababini batafsil yozing')
                ->maxLength(1000)
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('incurred_on')->label('Sana')->date()->sortable(),
                TextColumn::make('store.name')->label('Filial')->sortable(),
                TextColumn::make('type')->label('Chiqim turi')->badge()->sortable(),
                TextColumn::make('description')->label('Sababi')->wrap()->limit(70)->searchable()->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Summa')
                    ->money('UZS', divideBy: 1, decimalPlaces: 0)
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->label('Faol chiqimlar jami')
                            ->using(fn (QueryBuilder $query): int => (int) $query
                                ->where('status', ExpenseStatus::Active->value)
                                ->sum('amount'))
                            ->money('UZS', divideBy: 1, decimalPlaces: 0),
                    ),
                TextColumn::make('status')->label('Holati')->badge()->sortable(),
                TextColumn::make('creator.name')->label('Kiritgan foydalanuvchi'),
                TextColumn::make('created_at')->label('Kiritilgan vaqt')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                DatePeriodFilter::make('incurred_on'),
                SelectFilter::make('type')->label('Chiqim turi')->options(collect(ExpenseType::cases())->mapWithKeys(
                    fn (ExpenseType $type): array => [$type->value => $type->getLabel()],
                )->all()),
                SelectFilter::make('status')->label('Holati')->options(collect(ExpenseStatus::cases())->mapWithKeys(
                    fn (ExpenseStatus $status): array => [$status->value => $status->getLabel()],
                )->all()),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['sm' => 2, 'lg' => 3])
            ->deferFilters(false)
            ->defaultSort('incurred_on', 'desc')
            ->recordActions([
                ViewAction::make()->label('Ko‘rish'),
                Action::make('cancel')
                    ->label('Bekor qilish')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->visible(fn (Expense $record): bool => Gate::allows('cancel', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Chiqimni bekor qilish')
                    ->modalDescription('Yozuv o‘chirilmaydi va bekor qilingan holatda tarixda qoladi.')
                    ->modalSubmitActionLabel('Bekor qilish')
                    ->schema([
                        Textarea::make('cancellation_reason')
                            ->label('Bekor qilish sababi')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (Expense $record, array $data): void {
                        Gate::authorize('cancel', $record);
                        app(CancelExpense::class)->execute($record, request()->user(), $data['cancellation_reason']);
                        Notification::make()->success()->title('Chiqim bekor qilindi')->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                Group::make()->schema([
                    Section::make('Chiqim tafsilotlari')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('status')->label('Holati')->badge(),
                                TextEntry::make('type')->label('Chiqim turi')->badge(),
                                TextEntry::make('store.name')->label('Filial')->icon('heroicon-m-building-storefront'),
                                TextEntry::make('creator.name')->label('Kiritgan foydalanuvchi')->icon('heroicon-m-user'),
                                TextEntry::make('incurred_on')->label('Sana')->date(),
                                TextEntry::make('description')->label('Sababi')->placeholder('—')->columnSpanFull(),
                            ]),
                        ]),
                    Section::make('Bekor qilinganligi haqida ma\'lumot')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('canceller.name')->label('Bekor qilgan foydalanuvchi')->icon('heroicon-m-user')->placeholder('—'),
                                TextEntry::make('cancelled_at')->label('Bekor qilingan vaqt')->dateTime()->placeholder('—'),
                                TextEntry::make('cancellation_reason')->label('Bekor qilish sababi')->placeholder('—')->columnSpanFull(),
                            ]),
                        ])
                        ->visible(fn (Expense $record): bool => $record->status === ExpenseStatus::Cancelled),
                ])->columnSpan(['default' => 3, 'md' => 2]),

                Group::make()->schema([
                    Section::make('Moliya')
                        ->schema([
                            TextEntry::make('amount')->label('Summa')
                                ->size(TextSize::Large)
                                ->weight(FontWeight::Bold)
                                ->money('UZS', divideBy: 1, decimalPlaces: 0)
                                ->color(fn (Expense $record) => $record->status === ExpenseStatus::Cancelled ? 'gray' : 'danger'),
                        ]),
                ])->columnSpan(['default' => 3, 'md' => 1]),
            ])->columnSpanFull(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['store', 'creator', 'canceller'])
            ->forTenant(app(TenantContext::class)->requireCurrent())
            ->forStore(app(StoreContext::class)->requireCurrent());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'view' => ViewExpense::route('/{record}'),
        ];
    }
}
