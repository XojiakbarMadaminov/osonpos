import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiService, type CreatedOrder } from './api';

afterEach(() => vi.unstubAllGlobals());

describe('POS order API', () => {
    it('cancels an open order through the protected cancellation endpoint', async () => {
        vi.stubGlobal('document', { querySelector: () => null });
        const cancelledOrder = { id: '01ORDER', status: 'CANCELLED' } as CreatedOrder;
        const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: cancelledOrder }), { status: 200 }));
        vi.stubGlobal('fetch', fetch);
        const api = new ApiService({ headers: () => ({}) } as never);

        await expect(api.cancelOrder('01ORDER')).resolves.toEqual(cancelledOrder);
        expect(fetch).toHaveBeenCalledOnce();
        expect(fetch.mock.calls[0]?.[0]).toBe('/api/pos/orders/01ORDER/cancel');
        expect(fetch.mock.calls[0]?.[1]?.method).toBe('POST');
    });
});
