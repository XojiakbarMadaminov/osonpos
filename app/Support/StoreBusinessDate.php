<?php

namespace App\Support;

use App\Models\Store;

class StoreBusinessDate
{
    public function current(Store $store): string
    {
        return now($store->timezone)->toDateString();
    }
}
