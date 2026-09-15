import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import type { CreatedOrder } from '../services/api';
import { useOrderStore } from './order';

const openOrder: CreatedOrder = {
    id: '01TEST',
    display_number: '#0001',
    type: 'DINE_IN',
    status: 'OPEN',
    payment_status: 'UNPAID',
    table_id: 7,
    table: { id: 7, name: 'Terrace 7', number: '7' },
    customer_id: null,
    customer: null,
    subtotal: 45000,
    delivery_fee: 0,
    total: 45000,
    paid_amount: 0,
    balance_due: 45000,
    unprinted_items_count: 0,
    pending_item_removals_count: 0,
};

describe('order store', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('opens an existing table order for adding products', () => {
        const order = useOrderStore();
        order.openExisting(openOrder);

        expect(order.current?.id).toBe(openOrder.id);
        expect(order.type).toBe('DINE_IN');
        expect(order.tableId).toBe(7);
    });

    it('clears an existing order when a free table is selected', () => {
        const order = useOrderStore();
        order.openExisting(openOrder);
        order.selectTable(8);

        expect(order.current).toBeNull();
        expect(order.tableId).toBe(8);
    });

    it('restores and clears optional customer selection with order flow', () => {
        const order = useOrderStore();
        const customer = { id: '01CUSTOMER', name: 'Ali', phone: '+998901234567' };
        order.openExisting({ ...openOrder, customer_id: customer.id, customer });

        expect(order.selectedCustomer).toEqual(customer);
        order.start('TAKEAWAY');
        expect(order.selectedCustomer).toBeNull();
    });
});
