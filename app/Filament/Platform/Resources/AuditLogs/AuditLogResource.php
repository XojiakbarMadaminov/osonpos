<?php

namespace App\Filament\Platform\Resources\AuditLogs;

use App\Filament\Platform\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('organization.name')->searchable(),
                TextColumn::make('event')->badge()->searchable(),
                TextColumn::make('actor.name')->placeholder('System'),
                TextColumn::make('auditable_type')->label('Resource')->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextColumn::make('auditable_id')->label('Resource ID'),
                TextColumn::make('old_values_summary')->label('Before')->state(fn (AuditLog $record): string => json_encode($record->old_values) ?: '—')->wrap(),
                TextColumn::make('new_values_summary')->label('After')->state(fn (AuditLog $record): string => json_encode($record->new_values) ?: '—')->wrap(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['organization', 'actor']);
    }

    public static function canViewAny(): bool
    {
        return request()->user()?->is_platform_admin === true;
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
