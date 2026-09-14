<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DatePeriodFilter
{
    public static function make(string $column): Filter
    {
        return Filter::make('date_period')
            ->label('Davr')
            ->schema([
                ToggleButtons::make('period')
                    ->label('Davr')
                    ->hiddenLabel()
                    ->options([
                        'TODAY' => 'Bugun',
                        'WEEK' => 'Hafta',
                        'MONTH' => 'Oy',
                        'CUSTOM' => 'Oraliq',
                    ])
                    ->default('TODAY')
                    ->inline()
                    ->grouped()
                    ->live()
                    ->columnSpanFull(),
                DatePicker::make('from')
                    ->label('Boshlanish sanasi')
                    ->default(today())
                    ->visible(fn (Get $get): bool => $get('period') === 'CUSTOM')
                    ->required(fn (Get $get): bool => $get('period') === 'CUSTOM'),
                DatePicker::make('until')
                    ->label('Tugash sanasi')
                    ->default(today())
                    ->visible(fn (Get $get): bool => $get('period') === 'CUSTOM')
                    ->required(fn (Get $get): bool => $get('period') === 'CUSTOM')
                    ->afterOrEqual('from'),
            ])
            ->default([
                'period' => 'TODAY',
                'from' => today()->toDateString(),
                'until' => today()->toDateString(),
            ])
            ->query(function (Builder $query, array $data) use ($column): Builder {
                [$from, $until] = match ($data['period'] ?? 'TODAY') {
                    'WEEK' => [today()->startOfWeek(), today()->endOfWeek()],
                    'MONTH' => [today()->startOfMonth(), today()->endOfMonth()],
                    'CUSTOM' => [$data['from'] ?? null, $data['until'] ?? null],
                    default => [today(), today()],
                };

                return $query
                    ->when($from, fn (Builder $query, mixed $from): Builder => $query->whereDate($column, '>=', $from))
                    ->when($until, fn (Builder $query, mixed $until): Builder => $query->whereDate($column, '<=', $until));
            })
            ->indicateUsing(fn (array $data): string => match ($data['period'] ?? 'TODAY') {
                'WEEK' => 'Davr: Hafta',
                'MONTH' => 'Davr: Oy',
                'CUSTOM' => 'Davr: '.($data['from'] ?? '—').' — '.($data['until'] ?? '—'),
                default => 'Davr: Bugun',
            });
    }
}
