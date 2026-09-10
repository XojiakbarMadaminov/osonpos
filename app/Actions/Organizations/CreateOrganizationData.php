<?php

namespace App\Actions\Organizations;

use Carbon\CarbonImmutable;

readonly class CreateOrganizationData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $phone,
        public string $storeName,
        public ?string $storeAddress,
        public ?string $storePhone,
        public string $timezone,
        public int $planId,
        public int $ownerId,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}
