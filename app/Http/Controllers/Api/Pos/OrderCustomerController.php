<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Orders\SetOpenOrderCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\SetOrderCustomerRequest;
use App\Http\Resources\Pos\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderCustomerController extends Controller
{
    public function update(
        SetOrderCustomerRequest $request,
        Order $order,
        SetOpenOrderCustomer $setCustomer,
        TenantContext $tenantContext,
    ): OrderResource {
        Gate::authorize('update', $order);
        $customer = Customer::query()
            ->forTenant($tenantContext->requireCurrent())
            ->find($request->validated('customer_id'));

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Mijoz joriy tashkilotga tegishli emas.',
            ]);
        }

        return new OrderResource($setCustomer->execute($order, $customer)->load('table'));
    }

    public function destroy(Order $order, SetOpenOrderCustomer $setCustomer): OrderResource
    {
        Gate::authorize('update', $order);

        return new OrderResource($setCustomer->execute($order, null)->load('table'));
    }
}
