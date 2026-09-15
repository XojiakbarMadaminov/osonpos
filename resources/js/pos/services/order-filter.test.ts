import { describe, expect, it } from 'vitest';
import type { CreatedOrder } from './api';
import { countOrdersByStatus, filterOrdersByStatus } from './order-filter';

const orders: CreatedOrder[] = [
    { id: '1', display_number: '#0001', type: 'DINE_IN', status: 'OPEN', payment_status: 'UNPAID', table_id: 1, table: { id: 1, name: 'Table 1', number: '1' }, customer_id: null, customer: null, subtotal: 10000, delivery_fee: 0, total: 10000, paid_amount: 0, balance_due: 10000, unprinted_items_count: 1, pending_item_removals_count: 0 },
    { id: '2', display_number: '#0002', type: 'TAKEAWAY', status: 'COMPLETED', payment_status: 'PAID', table_id: null, table: null, customer_id: null, customer: null, subtotal: 20000, delivery_fee: 0, total: 20000, paid_amount: 20000, balance_due: 0, unprinted_items_count: 0, pending_item_removals_count: 0 },
    { id: '3', display_number: '#0003', type: 'DELIVERY', status: 'CANCELLED', payment_status: 'UNPAID', table_id: null, table: null, customer_id: null, customer: null, subtotal: 30000, delivery_fee: 5000, total: 35000, paid_amount: 0, balance_due: 35000, unprinted_items_count: 0, pending_item_removals_count: 0 },
];

describe('order status filtering', () => {
    it('filters and counts orders by status', () => {
        expect(filterOrdersByStatus(orders, 'OPEN').map((order) => order.id)).toEqual(['1']);
        expect(countOrdersByStatus(orders, 'COMPLETED')).toBe(1);
        expect(countOrdersByStatus(orders, 'CANCELLED')).toBe(1);
    });
});
