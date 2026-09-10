import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from './auth';

describe('auth store', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('drives permission-based action visibility', () => {
        const auth = useAuthStore();
        auth.hydrate({ id: 1, name: 'Cashier', email: 'cashier@example.test' }, ['pos.access', 'orders.create']);

        expect(auth.can('orders.create')).toBe(true);
        expect(auth.can('orders.cancel')).toBe(false);
    });
});
