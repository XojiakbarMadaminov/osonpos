import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useCartStore } from './cart';

const product = { id: 10, category_id: 2, name: 'Lavash', price: 28000 };

describe('cart store', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('adds products instantly in local Pinia state', () => {
        const cart = useCartStore();
        cart.add(product);
        cart.add(product);

        expect(cart.items).toHaveLength(1);
        expect(cart.items[0]?.quantity).toBe(2);
        expect(cart.count).toBe(2);
        expect(cart.subtotal).toBe(56000);
    });

    it('supports local item notes and removal without an API dependency', () => {
        const cart = useCartStore();
        cart.add(product);
        cart.setNote(0, 'No onions');
        cart.decrement(0);

        expect(cart.items).toEqual([]);
    });
});
