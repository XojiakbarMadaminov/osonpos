import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiService } from './api';
import { complementaryPaymentAmount, normalizedPaymentAmount, validMixedPayment } from './payment-flow';

afterEach(() => vi.unstubAllGlobals());

describe('POS mixed payment flow', () => {
    it('keeps cash and card complementary to the remaining balance', () => {
        expect(complementaryPaymentAmount(80000, 50000)).toBe(30000);
        expect(complementaryPaymentAmount(80000, 30000)).toBe(50000);
        expect(normalizedPaymentAmount(80000, 90000)).toBe(80000);
        expect(validMixedPayment(80000, 30000, 50000)).toBe(true);
        expect(validMixedPayment(80000, 0, 80000)).toBe(false);
    });

    it('sends both payment parts in one idempotent API request', async () => {
        vi.stubGlobal('document', { querySelector: () => null });
        const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: {} }), { status: 201 }));
        vi.stubGlobal('fetch', fetch);
        const api = new ApiService({ headers: () => ({}) } as never);

        await api.createMixedPayment('01ORDER', '01CASH', 30000, '01CARD', 50000);

        expect(fetch).toHaveBeenCalledOnce();
        expect(fetch.mock.calls[0]?.[0]).toBe('/api/pos/orders/01ORDER/mixed-payments');
        expect(JSON.parse(fetch.mock.calls[0]?.[1]?.body as string)).toEqual({
            cash_payment_id: '01CASH',
            cash_amount: 30000,
            card_payment_id: '01CARD',
            card_amount: 50000,
        });
    });
});
