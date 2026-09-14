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

class PrepareKitchenTicket
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly DeviceContext $deviceContext,
        private readonly PrinterRoutingService $routing,
    ) {}

    public function execute(Order $order, bool $reprint = false): KitchenTicketData
    {
        $this->ensureCurrentOrder($order, $reprint);
        $items = $order->items()
            ->when(! $reprint, fn ($query) => $query->whereNull('kitchen_printed_at'))
            ->orderBy('created_at')
            ->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => $reprint ? 'Bu buyurtmada qayta chop etiladigan mahsulotlar yo‘q.' : 'Oshxonaga chop etiladigan yangi mahsulotlar yo‘q.',
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

        return new KitchenTicketData(
            order: $order->loadMissing(['store', 'table', 'creator']),
            printer: $printer,
            items: $items,
            isReprint: $reprint,
        );
    }

    private function ensureCurrentOrder(Order $order, bool $reprint): void
    {
        $validStatus = $reprint
            ? $order->status !== OrderStatus::Cancelled
            : $order->status === OrderStatus::Open;

        if ((int) $order->organization_id !== (int) $this->tenantContext->requireCurrent()->getKey()
            || (int) $order->store_id !== (int) $this->storeContext->requireCurrent()->getKey()
            || ! $validStatus) {
            throw ValidationException::withMessages(['order' => 'Bu buyurtmani joriy muhitda chop etib bo‘lmaydi.']);
        }
    }
}
