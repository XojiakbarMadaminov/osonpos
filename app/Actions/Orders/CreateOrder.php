<?php

namespace App\Actions\Orders;

use App\Actions\Customers\FindOrCreateCustomer;
use App\Domain\Shift\CurrentShift;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use App\Support\DeviceContext;
use App\Support\StoreBusinessDate;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly StoreBusinessDate $businessDate,
        private readonly DeviceContext $deviceContext,
        private readonly FindOrCreateCustomer $customers,
        private readonly CurrentShift $currentShift,
    ) {}

    public function execute(User $user, CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $organization = $this->tenantContext->requireCurrent();
            $store = Store::query()->lockForUpdate()->findOrFail($this->storeContext->requireCurrent()->getKey());
            $id = $data->id ?? (string) Str::ulid();
            $existing = Order::query()->whereKey($id)->first();

            if ($existing) {
                $this->ensureCurrentScope($existing);

                return $existing;
            }

            if (! $this->currentShift->for($user)) {
                throw ValidationException::withMessages([
                    'shift' => 'Buyurtma olish uchun avval smenani oching.',
                ]);
            }

            $table = $this->resolveTable($data, $organization->getKey(), $store->getKey());
            $customer = $this->resolveCustomer($data, $organization->getKey());
            $deliveryFee = $data->type === OrderType::Delivery ? $data->deliveryFee : 0;
            $businessDate = $this->businessDate->current($store);
            $displayNumber = $this->nextDisplayNumber($store, $businessDate);

            $order = new Order([
                'type' => $data->type,
                'table_id' => $table?->getKey(),
                'customer_id' => $customer?->getKey(),
                'customer_name' => $customer?->name,
                'customer_phone' => $customer?->phone,
                'note' => $data->note,
            ]);
            $order->setAttribute('id', $id);
            $order->organization()->associate($organization);
            $order->store()->associate($store);
            $order->device()->associate($this->deviceContext->current());
            $order->creator()->associate($user);
            $order->forceFill([
                'display_number' => $displayNumber,
                'business_date' => $businessDate,
                'status' => OrderStatus::Open,
                'payment_status' => PaymentStatus::Unpaid,
                'subtotal' => 0,
                'delivery_fee' => $deliveryFee,
                'total' => $deliveryFee,
                'opened_at' => now(),
            ])->save();

            if ($data->type === OrderType::Delivery) {
                $detail = new DeliveryDetail([
                    'order_id' => $order->getKey(),
                    'address' => $data->deliveryAddress,
                    'delivery_fee' => $deliveryFee,
                    'note' => $data->deliveryNote,
                ]);
                $detail->organization()->associate($organization);
                $detail->store()->associate($store);
                $detail->save();
            }

            return $order->load('deliveryDetail');
        });
    }

    private function resolveTable(CreateOrderData $data, int $organizationId, int $storeId): ?Table
    {
        if ($data->type !== OrderType::DineIn) {
            return null;
        }

        $table = Table::query()
            ->whereKey($data->tableId)
            ->where('organization_id', $organizationId)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->first();

        return $table ?? throw ValidationException::withMessages([
            'table_id' => 'Joriy filialdan faol stolni tanlang.',
        ]);
    }

    private function resolveCustomer(CreateOrderData $data, int $organizationId): ?Customer
    {
        if ($data->customerId === null) {
            if ($data->customerPhone !== null) {
                return $this->customers->execute($data->customerPhone, $data->customerName);
            }

            if ($data->type === OrderType::Delivery) {
                throw ValidationException::withMessages(['customer.phone' => 'Yetkazib berish uchun mijoz telefoni kiritilishi shart.']);
            }

            return null;
        }

        return Customer::query()
            ->whereKey($data->customerId)
            ->where('organization_id', $organizationId)
            ->first() ?? throw ValidationException::withMessages([
                'customer_id' => 'Mijoz joriy tashkilotga tegishli emas.',
            ]);
    }

    private function nextDisplayNumber(Store $store, string $businessDate): string
    {
        $last = Order::query()
            ->where('store_id', $store->getKey())
            ->where('business_date', $businessDate)
            ->orderByRaw('CAST(SUBSTRING(display_number FROM 2) AS BIGINT) DESC')
            ->value('display_number');

        return '#'.str_pad((string) (((int) ltrim((string) $last, '#')) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function ensureCurrentScope(Order $order): void
    {
        if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()) {
            throw ValidationException::withMessages(['id' => 'Buyurtma identifikatori boshqa muhitga tegishli.']);
        }
    }
}
