import { defineStore } from 'pinia';
import type { PosProduct } from '../types/bootstrap';

export interface CartItem {
    productId: number;
    name: string;
    unitPrice: number;
    quantity: number;
    note: string;
}

export const useCartStore = defineStore('cart', {
    state: () => ({ items: [] as CartItem[] }),
    getters: {
        subtotal: (state): number => state.items.reduce((sum, item) => sum + item.unitPrice * item.quantity, 0),
        count: (state): number => state.items.reduce((sum, item) => sum + item.quantity, 0),
    },
    actions: {
        add(product: PosProduct): void {
            const existing = this.items.find((item) => item.productId === product.id && item.note === '');
            if (existing) {
                existing.quantity += 1;
                return;
            }
            this.items.push({
                productId: product.id,
                name: product.name,
                unitPrice: product.price,
                quantity: 1,
                note: '',
            });
        },
        decrement(index: number): void {
            const item = this.items[index];
            if (!item) return;
            item.quantity -= 1;
            if (item.quantity === 0) this.items.splice(index, 1);
        },
        setNote(index: number, note: string): void {
            const item = this.items[index];
            if (item) item.note = note;
        },
        clear(): void {
            this.items = [];
        },
    },
});
