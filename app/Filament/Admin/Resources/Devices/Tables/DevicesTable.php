<?php

namespace App\Filament\Admin\Resources\Devices\Tables;

use App\Actions\Devices\SetDeviceActivationCode;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')->label('Filial')->searchable(),
                TextColumn::make('name')->label('Nomi')->searchable(),
                IconColumn::make('is_active')->label('Faol')->boolean(),
                TextColumn::make('activated_at')->label('Aktivatsiya qilingan')->dateTime()->placeholder('Ulanmagan')->sortable(),
                TextColumn::make('last_seen_at')->label('Oxirgi faollik')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('created_at')
                    ->label('Yaratilgan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('activation_code')
                    ->label(fn (Device $record): string => $record->activation_code_hash === null
                        ? 'Doimiy kodni o‘rnatish'
                        : 'Doimiy kodni almashtirish')
                    ->icon('heroicon-o-key')
                    ->visible(fn (Device $record): bool => $record->is_active
                        && request()->user()->can('update', $record))
                    ->modalHeading('Doimiy aktivatsiya kodini belgilang')
                    ->modalDescription('Kod uni almashtirmaguningizcha qayta ishlatilishi mumkin.')
                    ->schema([
                        TextInput::make('activation_code')
                            ->label('Aktivatsiya kodi')
                            ->helperText('6 xonali raqam kiriting.')
                            ->required()
                            ->length(6)
                            ->rules(['digits:6'])
                            ->inputMode('numeric')
                            ->extraInputAttributes(['pattern' => '[0-9]{6}'])
                            ->password()
                            ->revealable(),
                    ])
                    ->action(function (Device $record, array $data): void {
                        abort_unless($record->is_active && request()->user()->can('update', $record), 403);
                        app(SetDeviceActivationCode::class)->execute($record, $data['activation_code']);

                        Notification::make()
                            ->success()
                            ->title('Doimiy aktivatsiya kodi saqlandi')
                            ->body('Kod POS terminalni ulashda qayta ishlatilishi mumkin.')
                            ->send();
                    }),
                Action::make('revoke')
                    ->label('Ulanishni bekor qilish')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Device $record): bool => $record->credential_hash !== null
                        && request()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->action(function (Device $record): void {
                        abort_unless(request()->user()->can('update', $record), 403);
                        $record->forceFill([
                            'credential_hash' => null,
                            'activated_at' => null,
                        ])->save();

                        Notification::make()->success()->title('Qurilma ulanishi bekor qilindi')->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
