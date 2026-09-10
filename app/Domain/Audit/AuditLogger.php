<?php

namespace App\Domain\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    private const SENSITIVE_KEYS = ['password', 'token', 'secret', 'private_key', 'certificate'];

    public function record(
        AuditEvent $event,
        Model $subject,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
        ?int $organizationId = null,
        ?int $storeId = null,
    ): AuditLog {
        $organizationId ??= isset($subject->organization_id) ? (int) $subject->organization_id : null;
        $storeId ??= isset($subject->store_id) ? (int) $subject->store_id : null;

        if (! $organizationId) {
            throw new \LogicException('Audit entries require an organization.');
        }

        return AuditLog::query()->forceCreate([
            'organization_id' => $organizationId,
            'store_id' => $storeId,
            'actor_id' => ($actor ?? auth()->user())?->getKey(),
            'event' => $event,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => (string) $subject->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => request()->ip(),
        ]);
    }

    private function sanitize(array $values): array
    {
        return collect($values)
            ->reject(fn (mixed $value, string $key): bool => in_array(strtolower($key), self::SENSITIVE_KEYS, true))
            ->except(['created_at', 'updated_at'])
            ->map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value)
            ->all();
    }
}
