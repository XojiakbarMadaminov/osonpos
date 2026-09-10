<?php

namespace App\Filament\Admin\Resources\Users;

use App\Domain\Authorization\OrganizationAuthorization;
use App\Domain\Authorization\StoreAccess;
use App\Enums\OrganizationRole;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Support\TenantContext;
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
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->minLength(8),
            Select::make('role_id')
                ->options(fn (): array => static::roleOptions())
                ->required()
                ->searchable(),
            Select::make('store_ids')
                ->label('Stores')
                ->multiple()
                ->options(fn (): array => app(TenantContext::class)->requireCurrent()->stores()
                    ->whereIn('id', app(StoreAccess::class)->accessibleStoreIds(request()->user()))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->badge(),
                TextColumn::make('stores.name')->badge(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['stores', 'roles'])
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey(app(TenantContext::class)->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function roleOptions(): array
    {
        $organization = app(TenantContext::class)->requireCurrent();
        $actor = request()->user();
        $isOwner = app(OrganizationAuthorization::class)->runForUserInTenant(
            $actor,
            $organization,
            fn (User $tenantUser): bool => $tenantUser->hasRole(OrganizationRole::Owner->value),
        );

        return Role::query()
            ->where('organization_id', $organization->getKey())
            ->when(! $isOwner, fn (Builder $query) => $query->whereIn('name', [OrganizationRole::Cashier->value, OrganizationRole::Waiter->value]))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
