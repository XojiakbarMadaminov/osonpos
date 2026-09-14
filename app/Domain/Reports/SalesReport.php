<?php

namespace App\Domain\Reports;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SalesReport
{
    public function generate(
        Organization $organization,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $storeIds,
    ): array {
        $orders = DB::table('orders')
            ->where('organization_id', $organization->getKey())
            ->whereIn('store_id', $storeIds)
            ->where('status', OrderStatus::Completed->value)
            ->whereBetween('opened_at', [$from, $to]);

        $summary = (clone $orders)->selectRaw('COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS revenue')->first();
        $orderCount = (int) $summary->order_count;
        $revenue = (int) $summary->revenue;

        $payments = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.organization_id', $organization->getKey())
            ->whereIn('payments.store_id', $storeIds)
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereBetween('orders.opened_at', [$from, $to]);

        $paymentBreakdown = (clone $payments)
            ->selectRaw('payments.method, SUM(payments.amount) AS total')
            ->groupBy('payments.method')
            ->orderBy('payments.method')
            ->get()
            ->map(fn (object $row): array => [
                'label' => PaymentMethod::tryFrom($row->method)?->getLabel() ?? $row->method,
                'total' => (int) $row->total,
            ])
            ->all();

        $orderTypes = (clone $orders)
            ->selectRaw('type, COUNT(*) AS total')
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->map(fn (object $row): array => [
                'label' => OrderType::tryFrom($row->type)?->getLabel() ?? $row->type,
                'total' => (int) $row->total,
            ])
            ->all();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.organization_id', $organization->getKey())
            ->whereIn('order_items.store_id', $storeIds)
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereBetween('orders.opened_at', [$from, $to])
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) AS quantity, SUM(order_items.total) AS revenue')
            ->groupBy('order_items.product_name')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->product_name,
                'quantity' => (int) $row->quantity,
                'revenue' => (int) $row->revenue,
            ])
            ->all();

        return [
            'revenue' => $revenue,
            'order_count' => $orderCount,
            'average_check' => $orderCount > 0 ? intdiv($revenue, $orderCount) : 0,
            'payment_breakdown' => $paymentBreakdown,
            'order_type_breakdown' => $orderTypes,
            'top_products' => $topProducts,
        ];
    }
}
