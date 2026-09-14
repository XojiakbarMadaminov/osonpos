import type { CreatedOrder } from './api';

export type OrderStatusFilter = CreatedOrder['status'];

export const orderStatusFilters: Array<{ label: string; value: OrderStatusFilter }> = [
    { label: 'Ochiq', value: 'OPEN' },
    { label: 'Yakunlangan', value: 'COMPLETED' },
    { label: 'Bekor qilingan', value: 'CANCELLED' },
];

export function orderStatusLabel(status: CreatedOrder['status']): string {
    return orderStatusFilters.find((item) => item.value === status)?.label ?? status;
}

export function orderTypeLabel(type: CreatedOrder['type']): string {
    return {
        DINE_IN: 'Zalda',
        TAKEAWAY: 'Olib ketish',
        DELIVERY: 'Yetkazib berish',
    }[type];
}

export function filterOrdersByStatus(orders: CreatedOrder[], status: OrderStatusFilter): CreatedOrder[] {
    return orders.filter((order) => order.status === status);
}

export function countOrdersByStatus(orders: CreatedOrder[], status: OrderStatusFilter): number {
    return filterOrdersByStatus(orders, status).length;
}
