import type { CreatedOrder } from './api';

export type OrderStatusFilter = CreatedOrder['status'];

export const orderStatusFilters: Array<{ label: string; value: OrderStatusFilter }> = [
    { label: 'Open', value: 'OPEN' },
    { label: 'Completed', value: 'COMPLETED' },
    { label: 'Cancelled', value: 'CANCELLED' },
];

export function filterOrdersByStatus(orders: CreatedOrder[], status: OrderStatusFilter): CreatedOrder[] {
    return orders.filter((order) => order.status === status);
}

export function countOrdersByStatus(orders: CreatedOrder[], status: OrderStatusFilter): number {
    return filterOrdersByStatus(orders, status).length;
}
