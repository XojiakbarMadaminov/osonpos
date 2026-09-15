<?php

namespace App\Actions\Printing;

use App\Domain\Printing\PrinterRoutingService;
use App\Enums\OrderStatus;
use App\Enums\PrintType;
use App\Models\Order;
use App\Support\DeviceContext;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

class PrepareKitchenRemovalTicket
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
        private readonly PrinterRoutingService $routing,
    ) {}

    public function execute(Order $order, bool $reprint = false): KitchenRemovalTicketData
    {
        $validStatus = $reprint || in_array($order->status, [OrderStatus::Open, OrderStatus::Cancelled], true);
        if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
            || ! $validStatus) {
            throw ValidationException::withMessages(['order' => 'Bu buyurtma ayirish chekini joriy muhitda chop etib bo‘lmaydi.']);
        }

        $removals = $order->itemRemovals()
            ->where('kitchen_print_required', true)
            ->when(! $reprint, fn ($query) => $query->whereNull('kitchen_printed_at'))
            ->with('orderItem')
            ->orderBy('created_at')
            ->get();

        if ($removals->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => $reprint
                    ? 'Qayta chop etiladigan mahsulot ayirishlari yo‘q.'
                    : 'Oshxonaga chop etiladigan mahsulot ayirishlari yo‘q.',
            ]);
        }

        $printer = $this->routing->resolve(
            PrintType::KitchenTicket,
            $this->storeContext->requireCurrent(),
            $this->deviceContext->requireCurrent(),
        );

        if (! $printer->system_name) {
            throw ValidationException::withMessages(['printer' => 'Bu qurilmaga oshxona printeri biriktirilmagan.']);
        }

        return new KitchenRemovalTicketData(
            order: $order->loadMissing(['store', 'table']),
            printer: $printer,
            removals: $removals,
            isReprint: $reprint,
        );
    }
}
