<?php

namespace App\Actions\Printing;

use App\Domain\Printing\PrinterRoutingService;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintType;
use App\Models\Order;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

class PrepareCustomerReceipt
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
        private readonly PrinterRoutingService $routing,
    ) {}

    public function execute(Order $order, bool $reprint = false): CustomerReceiptData
    {
        $isValid = (int) $order->organization_id === (int) $this->tenantContext->requireCurrent()->getKey()
            && (int) $order->store_id === (int) $this->storeContext->requireCurrent()->getKey()
            && $order->status !== OrderStatus::Cancelled
            && $order->payment_status === PaymentStatus::Paid;

        if (! $isValid) {
            throw ValidationException::withMessages(['order' => 'Chek uchun joriy filialdagi to‘langan buyurtma kerak.']);
        }

        $printer = $this->routing->resolve(
            PrintType::CustomerReceipt,
            $this->storeContext->requireCurrent(),
            $this->deviceContext->requireCurrent(),
        );

        if (! $printer->system_name) {
            throw ValidationException::withMessages(['printer' => 'Bu qurilmaga mijoz cheki printeri biriktirilmagan.']);
        }

        return new CustomerReceiptData(
            order: $order->loadMissing([
                'store',
                'table',
                'creator',
                'items.removals',
                'payments',
                'deliveryDetail',
            ]),
            printer: $printer,
            isReprint: $reprint,
        );
    }
}
