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
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(TenantContext $tenantContext, StoreContext $storeContext): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);
        $orders = Order::query()
            ->forTenant($tenantContext->requireCurrent())
            ->forStore($storeContext->requireCurrent())
            ->with('table:id,name,number')
            ->withSum('payments', 'amount')
            ->latest('opened_at')
            ->paginate(50);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(['items', 'deliveryDetail', 'table'])->loadSum('payments', 'amount'));
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
