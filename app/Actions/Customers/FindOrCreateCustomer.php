<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class FindOrCreateCustomer
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function find(string $phone): ?Customer
    {
        return Customer::query()
            ->forTenant($this->tenantContext->requireCurrent())
            ->where('phone', $this->normalizePhone($phone))
            ->first();
    }

    public function execute(string $phone, ?string $name = null): Customer
    {
        return DB::transaction(function () use ($phone, $name): Customer {
            $organization = $this->tenantContext->requireCurrent();
            $customer = Customer::query()->firstOrNew([
                'organization_id' => $organization->getKey(),
                'phone' => $this->normalizePhone($phone),
            ]);

            if (! $customer->exists || ($customer->name === null && $name !== null)) {
                $customer->name = $name;
            }

            $customer->organization()->associate($organization);
            $customer->save();

            return $customer;
        });
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', trim($phone));
    }
}
