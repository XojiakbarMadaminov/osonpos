<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Customers\FindOrCreateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\CreateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function store(CreateCustomerRequest $request, FindOrCreateCustomer $customers): JsonResponse
    {
        Gate::authorize('create', Customer::class);
        $customer = $customers->execute(
            $request->validated('phone'),
            $request->validated('name'),
        );

        return response()->json(['data' => $customer->only(['id', 'name', 'phone'])]);
    }
}
