<?php

namespace App\Observers;

use App\Domain\Audit\AuditLogger;
use App\Enums\AuditEvent;
use App\Models\Printer;
use App\Models\PrintRoute;
use App\Models\Product;
use App\Models\Store;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

class CriticalConfigurationObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        if ($model instanceof Product) {
            return;
        }

        if ($event = $this->eventFor($model)) {
            $this->audit->record($event, $model, newValues: $this->relevantChanges($model, $model->getAttributes()));
        }
    }

    public function updated(Model $model): void
    {
        $event = $this->eventFor($model);
        $changes = $this->relevantChanges($model, $model->getChanges());

        if (! $event || $changes === []) {
            return;
        }

        $old = collect(array_keys($changes))
            ->mapWithKeys(fn (string $key): array => [$key => $model->getRawOriginal($key)])
            ->all();

        $this->audit->record($event, $model, $old, $changes);
    }

    private function eventFor(Model $model): ?AuditEvent
    {
        return match (true) {
            $model instanceof Product => AuditEvent::ProductPriceChanged,
            $model instanceof Printer => AuditEvent::PrinterChanged,
            $model instanceof PrintRoute => AuditEvent::PrintRouteChanged,
            $model instanceof Store => AuditEvent::StoreChanged,
            $model instanceof Subscription => AuditEvent::SubscriptionChanged,
            default => null,
        };
    }

    private function relevantChanges(Model $model, array $changes): array
    {
        $allowed = match (true) {
            $model instanceof Product => ['price'],
            $model instanceof Printer => ['store_id', 'device_id', 'name', 'system_name', 'is_active'],
            $model instanceof PrintRoute => ['store_id', 'print_type', 'printer_id'],
            $model instanceof Store => ['name', 'address', 'phone', 'timezone', 'is_active'],
            $model instanceof Subscription => ['plan_id', 'status', 'starts_at', 'ends_at'],
            default => [],
        };

        return collect($changes)->only($allowed)->all();
    }
}
