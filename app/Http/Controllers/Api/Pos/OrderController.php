<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\CreateOrderData;
use App\Actions\Orders\UpdateOpenOrder;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\CreateOrderRequest;
use App\Http\Requests\Api\Pos\UpdateOrderRequest;
use App\Http\Resources\Pos\OrderResource;
use App\Models\Order;
use App\Support\StoreBusinessDate;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(
        TenantContext $tenantContext,
        StoreContext $storeContext,
        StoreBusinessDate $businessDate,
    ): AnonymousResourceCollection {
        Gate::authorize('viewAny', Order::class);
        $store = $storeContext->requireCurrent();
        $orders = Order::query()
            ->forTenant($tenantContext->requireCurrent())
            ->forStore($store)
            ->where('business_date', $businessDate->current($store))
            ->with('table:id,name,number')
            ->withSum('payments', 'amount')
            ->withCount([
                'items as unprinted_items_count' => fn ($query) => $query
                    ->whereNull('kitchen_printed_at')
                    ->whereRaw('order_items.quantity > COALESCE((SELECT SUM(order_item_removals.quantity) FROM order_item_removals WHERE order_item_removals.order_item_id = order_items.id), 0)'),
                'itemRemovals as pending_item_removals_count' => fn ($query) => $query
                    ->where('kitchen_print_required', true)
                    ->whereNull('kitchen_printed_at'),
            ])
            ->latest('opened_at')
            ->paginate(50);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource(
            $order->load(['items.removals', 'deliveryDetail', 'table'])
                ->loadSum('payments', 'amount')
                ->loadCount([
                    'items as unprinted_items_count' => fn ($query) => $query
                        ->whereNull('kitchen_printed_at')
                        ->whereRaw('order_items.quantity > COALESCE((SELECT SUM(order_item_removals.quantity) FROM order_item_removals WHERE order_item_removals.order_item_id = order_items.id), 0)'),
                    'itemRemovals as pending_item_removals_count' => fn ($query) => $query
                        ->where('kitchen_print_required', true)
                        ->whereNull('kitchen_printed_at'),
                ]),
        );
    }

    public function store(CreateOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        Gate::authorize('create', Order::class);
        $validated = $request->validated();
        $order = $createOrder->execute($request->user(), new CreateOrderData(
            id: $validated['id'] ?? null,
            type: OrderType::from($validated['type']),
            tableId: $validated['table_id'] ?? null,
            customerId: $validated['customer_id'] ?? null,
            customerPhone: data_get($validated, 'customer.phone'),
            customerName: data_get($validated, 'customer.name'),
            note: $validated['note'] ?? null,
            deliveryAddress: data_get($validated, 'delivery.address'),
            deliveryFee: data_get($validated, 'delivery.fee', 0),
            deliveryNote: data_get($validated, 'delivery.note'),
        ));

        return (new OrderResource($order->load('table')))
            ->additional(['created' => $order->wasRecentlyCreated])
            ->response()
            ->setStatusCode($order->wasRecentlyCreated ? 201 : 200);
    }

    public function update(
        UpdateOrderRequest $request,
        Order $order,
        UpdateOpenOrder $updateOrder,
    ): OrderResource {
        Gate::authorize('update', $order);

        return new OrderResource($updateOrder->execute($order, $request->validated('note'))->load('table'));
    }
}
