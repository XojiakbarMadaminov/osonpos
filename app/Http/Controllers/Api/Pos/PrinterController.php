<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Printing\BindPrinterToDevice;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\BindPrinterRequest;
use App\Models\Printer;
use App\Support\StoreContext;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrinterController extends Controller
{
    public function index(
        Request $request,
        OrganizationAuthorization $authorization,
        TenantContext $tenantContext,
        StoreContext $storeContext,
    ): JsonResponse {
        abort_unless($authorization->allows($request->user(), OrganizationPermission::PrintersManage), 403);

        $printers = Printer::query()
            ->where('organization_id', $tenantContext->requireCurrent()->getKey())
            ->where('store_id', $storeContext->requireCurrent()->getKey())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'system_name', 'device_id', 'paper_width']);

        return response()->json(['data' => $printers]);
    }

    public function bind(
        BindPrinterRequest $request,
        Printer $printer,
        BindPrinterToDevice $bindPrinter,
        OrganizationAuthorization $authorization,
    ): JsonResponse {
        abort_unless($authorization->allows($request->user(), OrganizationPermission::PrintersManage), 403);

        $printer = $bindPrinter->execute($printer, $request->validated('system_name'));

        return response()->json(['data' => [
            'id' => $printer->getKey(),
            'system_name' => $printer->system_name,
            'device_id' => $printer->device_id,
        ]]);
    }
}
