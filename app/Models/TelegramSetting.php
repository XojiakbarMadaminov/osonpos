<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group_chat_id'])]
class TelegramSetting extends Model
{
    use BelongsToTenant;
}
