<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Customers\FindOrCreateCustomer;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\LookupCustomerRequest;
use Illuminate\Http\JsonResponse;

class CustomerLookupController extends Controller
{
    public function __invoke(
        LookupCustomerRequest $request,
        FindOrCreateCustomer $customers,
        OrganizationAuthorization $authorization,
    ): JsonResponse {
        abort_unless($authorization->allows($request->user(), OrganizationPermission::OrdersCreate), 403);
        $customer = $customers->find($request->validated('phone'));

        return response()->json(['data' => $customer ? [
            'id' => $customer->getKey(),
            'name' => $customer->name,
            'phone' => $customer->phone,
        ] : null]);
    }
}
