<?php

namespace App\Domain\Organization\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForTenant(Builder $query, Organization|int $organization): Builder
    {
        return $query->where(
            $this->qualifyColumn('organization_id'),
            $organization instanceof Organization ? $organization->getKey() : $organization,
        );
    }

    public function belongsToTenant(Organization|int $organization): bool
    {
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;

        return (int) $this->getAttribute('organization_id') === (int) $organizationId;
    }
}
