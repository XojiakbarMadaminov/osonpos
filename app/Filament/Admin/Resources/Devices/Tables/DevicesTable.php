<?php

namespace App\Filament\Admin\Resources\Devices\Tables;

use App\Actions\Devices\GenerateDeviceActivationCode;
use App\Models\Device;
use Filament\Actions\Action;
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
                TextColumn::make('code')->label('Kodi')->searchable(),
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
                    ->label('Aktivatsiya kodi')
                    ->icon('heroicon-o-key')
                    ->visible(fn (Device $record): bool => $record->is_active
                        && request()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Yangi aktivatsiya kodi yaratilsinmi?')
                    ->modalDescription('Kod 10 daqiqa amal qiladi va faqat bir marta ishlatiladi.')
                    ->action(function (Device $record): void {
                        abort_unless($record->is_active && request()->user()->can('update', $record), 403);
                        $code = app(GenerateDeviceActivationCode::class)->execute($record);

                        Notification::make()
                            ->success()
                            ->title('Aktivatsiya kodi: '.$code)
                            ->body('Kodni 10 daqiqa ichida POS terminalga kiriting. Bu xabarni yopishdan oldin kodni nusxalang.')
                            ->persistent()
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
                            'activation_code_hash' => null,
                            'activation_expires_at' => null,
                        ])->save();

                        Notification::make()->success()->title('Qurilma ulanishi bekor qilindi')->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
