<?php

namespace App\Filament\Platform\Resources\Organizations;

use App\Enums\OrganizationStatus;
use App\Filament\Platform\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Platform\Resources\Organizations\Pages\ListOrganizations;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $modelLabel = 'tashkilot';

    protected static ?string $pluralModelLabel = 'tashkilotlar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->alphaDash()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('phone')->tel()->maxLength(255),
            Select::make('status')->options(OrganizationStatus::class)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('latestSubscription.plan')->withCount(['stores', 'users']))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('latestSubscription.plan.name')->label('Tarif'),
                TextColumn::make('latestSubscription.status')->label('Obuna')->badge(),
                TextColumn::make('latestSubscription.ends_at')->label('Tugash vaqti')->dateTime()->sortable(),
                TextColumn::make('stores_count')->label('Filiallar')->numeric(),
                TextColumn::make('users_count')->label('Foydalanuvchilar')->numeric(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return request()->user()?->is_platform_admin === true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && $record instanceof Organization;
    }
}
