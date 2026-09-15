<?php

namespace App\Filament\Admin\Resources\Expenses\Pages;

use App\Actions\Expenses\CreateExpense as CreateExpenseAction;
use App\Enums\ExpenseType;
use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Support\StoreContext;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateExpenseAction::class)->execute(
            request()->user(),
            app(StoreContext::class)->requireCurrent()->getKey(),
            ExpenseType::from($data['type']),
            (int) $data['amount'],
            $data['description'] ?? null,
            CarbonImmutable::parse($data['incurred_on']),
        );
    }
}
