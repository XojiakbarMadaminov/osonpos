import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const api = vi.hoisted(() => ({
    currentShift: vi.fn(),
    openShift: vi.fn(),
    closeShift: vi.fn(),
}));

vi.mock('../services/api', () => ({ apiService: api }));

import { useShiftStore } from './shift';

const openShift = {
    id: '01SHIFT',
    status: 'OPEN' as const,
    opening_cash: 100000,
    closing_cash: null,
    opened_at: '2026-09-11T10:00:00Z',
    closed_at: null,
    payment_totals: { CASH: 0, CARD: 0, CLICK: 0, PAYME: 0, OTHER: 0 },
    payments_total: 0,
    cash_payments_total: 0,
    expected_cash: 100000,
    cash_difference: null,
};

describe('shift store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('shares the loaded current shift across POS controls', async () => {
        api.currentShift.mockResolvedValue(openShift);
        const shift = useShiftStore();

        await shift.load();

        expect(shift.current).toEqual(openShift);
        expect(shift.loaded).toBe(true);
    });

    it('opens and closes the current shift', async () => {
        api.openShift.mockResolvedValue(openShift);
        api.closeShift.mockResolvedValue({ ...openShift, status: 'CLOSED', closing_cash: 125000 });
        const shift = useShiftStore();

        await shift.open(100000);
        expect(shift.current?.opening_cash).toBe(100000);

        await shift.close(125000);
        expect(api.closeShift).toHaveBeenCalledWith(openShift.id, 125000);
        expect(shift.current).toBeNull();
    });
});
