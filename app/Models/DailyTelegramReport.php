<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use App\Domain\Store\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_date'])]
class DailyTelegramReport extends Model
{
    use BelongsToStore, BelongsToTenant;

    protected function casts(): array
    {
        return [
            'business_date' => 'immutable_date',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
