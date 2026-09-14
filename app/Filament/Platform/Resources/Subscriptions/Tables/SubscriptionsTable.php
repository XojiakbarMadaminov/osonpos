<?php

namespace App\Filament\Platform\Resources\Subscriptions\Tables;

use App\Actions\Subscriptions\ManageSubscription;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Faollashtirish')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->action(function (Subscription $record): void {
                        app(ManageSubscription::class)->activate($record);
                        Notification::make()->success()->title('Obuna faollashtirildi')->send();
                    }),
                Action::make('extend')
                    ->label('30 kunga uzaytirish')
                    ->icon('heroicon-o-calendar-days')
                    ->requiresConfirmation()
                    ->action(function (Subscription $record): void {
                        app(ManageSubscription::class)->extend($record);
                        Notification::make()->success()->title('Obuna uzaytirildi')->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
