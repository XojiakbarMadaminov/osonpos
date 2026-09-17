<?php

namespace App\Domain\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
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
        ?CarbonImmutable $businessFrom = null,
        ?CarbonImmutable $businessTo = null,
    ): array {
        $orders = DB::table('orders')
            ->where('organization_id', $organization->getKey())
            ->whereIn('store_id', $storeIds)
            ->where('status', OrderStatus::Completed->value)
            ->whereBetween('opened_at', [$from, $to]);

        $summary = (clone $orders)->selectRaw('COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS revenue')->first();
        $orderCount = (int) $summary->order_count;
        $revenue = (int) $summary->revenue;

        $expenses = DB::table('expenses')
            ->where('organization_id', $organization->getKey())
            ->whereIn('store_id', $storeIds)
            ->where('status', ExpenseStatus::Active->value)
            ->whereBetween('incurred_on', [
                ($businessFrom ?? $from)->toDateString(),
                ($businessTo ?? $to)->toDateString(),
            ]);
        $expenseTotal = (int) (clone $expenses)->sum('amount');
        $expenseBreakdown = (clone $expenses)
            ->selectRaw('type, SUM(amount) AS total')
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->map(fn (object $row): array => [
                'label' => ExpenseType::tryFrom($row->type)?->getLabel() ?? $row->type,
                'total' => (int) $row->total,
            ])
            ->all();

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

        $removedItems = DB::table('order_item_removals')
            ->selectRaw('order_item_id, SUM(quantity) AS removed_quantity, SUM(total) AS removed_total')
            ->groupBy('order_item_id');
        $soldItems = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoinSub($removedItems, 'removed_items', 'removed_items.order_item_id', '=', 'order_items.id')
            ->where('order_items.organization_id', $organization->getKey())
            ->whereIn('order_items.store_id', $storeIds)
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereBetween('orders.opened_at', [$from, $to]);
        $productCostSummary = (clone $soldItems)
            ->selectRaw('COALESCE(SUM((order_items.quantity - COALESCE(removed_items.removed_quantity, 0)) * order_items.unit_cost), 0) AS total, COALESCE(SUM(order_items.quantity - COALESCE(removed_items.removed_quantity, 0)), 0) AS item_count')
            ->first();
        $productCostTotal = (int) $productCostSummary->total;

        $topProducts = (clone $soldItems)
            ->selectRaw('order_items.product_name, SUM(order_items.quantity - COALESCE(removed_items.removed_quantity, 0)) AS quantity, SUM(order_items.total - COALESCE(removed_items.removed_total, 0)) AS revenue')
            ->groupBy('order_items.product_name')
            ->havingRaw('SUM(order_items.quantity - COALESCE(removed_items.removed_quantity, 0)) > 0')
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
            'estimated_gross_profit' => $revenue - $productCostTotal,
            'expense_total' => $expenseTotal,
            'order_count' => $orderCount,
            'item_count' => (int) $productCostSummary->item_count,
            'average_check' => $orderCount > 0 ? intdiv($revenue, $orderCount) : 0,
            'payment_breakdown' => $paymentBreakdown,
            'order_type_breakdown' => $orderTypes,
            'expense_breakdown' => $expenseBreakdown,
            'top_products' => $topProducts,
        ];
    }
}
