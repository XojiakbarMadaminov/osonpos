<?php

namespace App\Domain\Store;

use App\Models\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableOccupancy
{
    public function isOccupied(Table $table): bool
    {
        if (! Schema::hasTable('orders')) {
            return false;
        }

        return DB::table('orders')
            ->where('organization_id', $table->organization_id)
            ->where('store_id', $table->store_id)
            ->where('table_id', $table->getKey())
            ->where('status', 'OPEN')
            ->exists();
    }
}
