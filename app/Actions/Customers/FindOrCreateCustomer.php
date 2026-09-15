<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $digits = preg_replace('/\D/', '', trim($phone)) ?? '';

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '998'.substr($digits, 1);
        }

        if (strlen($digits) !== 12 || ! str_starts_with($digits, '998')) {
            throw ValidationException::withMessages([
                'phone' => 'O‘zbekiston telefon raqamini to‘g‘ri kiriting.',
            ]);
        }

        return '+'.$digits;
    }
}
