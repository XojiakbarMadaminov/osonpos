import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiService, type CreatedOrder } from './api';
import { canManageCustomerAtPayment } from './customer-flow';

const order: CreatedOrder = {
    id: '01ORDER',
    display_number: '#0001',
    type: 'DINE_IN',
    status: 'OPEN',
    payment_status: 'UNPAID',
    table_id: 1,
    table: { id: 1, name: 'Stol 1', number: '1' },
    customer_id: '01CUSTOMER',
    customer: { id: '01CUSTOMER', name: 'Ali', phone: '+998901234567' },
    subtotal: 10000,
    delivery_fee: 0,
    total: 10000,
    paid_amount: 0,
    balance_due: 10000,
    unprinted_items_count: 0,
    pending_item_removals_count: 0,
};

function service(): ApiService {
    return new ApiService({ headers: () => ({}) } as never);
}

afterEach(() => vi.unstubAllGlobals());

describe('customer POS API flow', () => {
    it('offers optional customer management at payment for dine-in and takeaway only', () => {
        expect(canManageCustomerAtPayment('DINE_IN')).toBe(true);
        expect(canManageCustomerAtPayment('TAKEAWAY')).toBe(true);
        expect(canManageCustomerAtPayment('DELIVERY')).toBe(false);
    });

    it('looks up a customer only when explicitly requested', async () => {
        const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: order.customer })));
        vi.stubGlobal('fetch', fetch);

        await expect(service().findCustomerByPhone('+998901234567')).resolves.toEqual(order.customer);
        expect(fetch).toHaveBeenCalledOnce();
        expect(fetch.mock.calls[0]?.[0]).toContain('/api/pos/customers/lookup?phone=');
    });

    it('creates a customer and supports attach and remove requests', async () => {
        vi.stubGlobal('document', { querySelector: () => null });
        const fetch = vi.fn()
            .mockResolvedValueOnce(new Response(JSON.stringify({ data: order.customer })))
            .mockResolvedValueOnce(new Response(JSON.stringify({ data: order })))
            .mockResolvedValueOnce(new Response(JSON.stringify({ data: { ...order, customer_id: null, customer: null } })));
        vi.stubGlobal('fetch', fetch);
        const api = service();

        await expect(api.createCustomer('+998901234567', 'Ali')).resolves.toEqual(order.customer);
        await expect(api.setOrderCustomer(order.id, '01CUSTOMER')).resolves.toEqual(order);
        await expect(api.removeOrderCustomer(order.id)).resolves.toMatchObject({ customer: null });

        expect(fetch.mock.calls.map((call) => [call[0], call[1]?.method])).toEqual([
            ['/api/pos/customers', 'POST'],
            [`/api/pos/orders/${order.id}/customer`, 'PUT'],
            [`/api/pos/orders/${order.id}/customer`, 'DELETE'],
        ]);
    });
});
