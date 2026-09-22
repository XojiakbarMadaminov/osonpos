<?php

namespace App\Models;

use App\Domain\Organization\Concerns\BelongsToTenant;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'address', 'phone', 'timezone', 'is_active', 'is_qr_menu_enabled'])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Store $store): void {
            $store->menu_token ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_qr_menu_enabled' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('created_at');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    public function printRoutes(): HasMany
    {
        return $this->hasMany(PrintRoute::class);
    }

    public function deliveryDetails(): HasMany
    {
        return $this->hasMany(DeliveryDetail::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function dailyTelegramReports(): HasMany
    {
        return $this->hasMany(DailyTelegramReport::class);
    }
}
