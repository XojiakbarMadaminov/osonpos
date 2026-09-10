<script setup lang="ts">
import { computed, ref } from 'vue';
import { apiService } from '../services/api';
import { useCartStore } from '../stores/cart';
import { useOrderStore } from '../stores/order';
import type { PosBootstrap, PosProduct } from '../types/bootstrap';

const props = defineProps<{ bootstrap: PosBootstrap }>();
const emit = defineEmits<{ navigate: [page: string] }>();
const cart = useCartStore();
const order = useOrderStore();
const selectedCategory = ref<number | null>(props.bootstrap.categories[0]?.id ?? null);
const busy = ref(false);
const error = ref('');
const products = computed(() => props.bootstrap.products.filter(
    (product) => selectedCategory.value === null || product.category_id === selectedCategory.value,
));

function add(product: PosProduct): void {
    cart.add(product);
}

function chooseType(type: 'DINE_IN' | 'TAKEAWAY' | 'DELIVERY'): void {
    order.start(type);
    if (type === 'DINE_IN') emit('navigate', 'tables');
    if (type === 'DELIVERY') emit('navigate', 'delivery');
}

async function saveOrder(): Promise<void> {
    if (cart.items.length === 0) return;
    if (order.type === 'DINE_IN' && order.tableId === null) {
        emit('navigate', 'tables');
        return;
    }
    if (order.type === 'DELIVERY' && (!order.customerPhone || !order.deliveryAddress)) {
        emit('navigate', 'delivery');
        return;
    }
    busy.value = true;
    error.value = '';
    try {
        const created = await apiService.createOrder({
            type: order.type,
            tableId: order.tableId,
            customerPhone: order.customerPhone,
            customerName: order.customerName,
            deliveryAddress: order.deliveryAddress,
            deliveryFee: order.deliveryFee,
        });
        await apiService.addOrderItems(created.id, cart.items);
        created.subtotal = cart.subtotal;
        created.total = cart.subtotal + (order.type === 'DELIVERY' ? order.deliveryFee : 0);
        order.current = created;
        cart.clear();
        emit('navigate', 'payment');
    } catch {
        error.value = 'The order could not be saved. Check the current shift and connection.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section class="grid gap-5 lg:grid-cols-[13rem_1fr_22rem]">
        <aside class="rounded-xl border border-slate-800 bg-slate-900 p-3">
            <button v-for="category in bootstrap.categories" :key="category.id" class="mb-2 min-h-12 w-full rounded-lg px-4 text-left font-medium" :class="selectedCategory === category.id ? 'bg-amber-400 text-slate-950' : 'bg-slate-800'" type="button" @click="selectedCategory = category.id">
                {{ category.name }}
            </button>
        </aside>
        <div class="grid content-start grid-cols-2 gap-3 rounded-xl border border-slate-800 bg-slate-900 p-4 md:grid-cols-3 xl:grid-cols-4">
            <button v-for="product in products" :key="product.id" class="min-h-24 rounded-xl border border-slate-700 bg-slate-800 p-4 text-left hover:border-amber-400 active:scale-95" type="button" @click="add(product)">
                <span class="block font-semibold">{{ product.name }}</span>
                <span class="mt-2 block text-sm text-amber-300">{{ product.price.toLocaleString() }} UZS</span>
            </button>
        </div>
        <aside class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="grid grid-cols-3 gap-2">
                <button class="min-h-12 rounded-lg border border-slate-700 text-xs" :class="order.type === 'DINE_IN' && 'border-amber-400'" type="button" @click="chooseType('DINE_IN')">Dine in</button>
                <button class="min-h-12 rounded-lg border border-slate-700 text-xs" :class="order.type === 'TAKEAWAY' && 'border-amber-400'" type="button" @click="chooseType('TAKEAWAY')">Takeaway</button>
                <button class="min-h-12 rounded-lg border border-slate-700 text-xs" :class="order.type === 'DELIVERY' && 'border-amber-400'" type="button" @click="chooseType('DELIVERY')">Delivery</button>
            </div>
            <div class="mt-4 space-y-3">
                <div v-for="(item, index) in cart.items" :key="`${item.productId}-${index}`" class="rounded-lg bg-slate-800 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <button class="min-h-10 min-w-10 rounded bg-slate-700" type="button" @click="cart.decrement(index)">−</button>
                        <span class="flex-1 font-medium">{{ item.quantity }}× {{ item.name }}</span>
                        <span>{{ (item.quantity * item.unitPrice).toLocaleString() }}</span>
                    </div>
                    <input :value="item.note" class="mt-2 min-h-10 w-full rounded border border-slate-700 bg-slate-950 px-3 text-sm" placeholder="Item note" @input="cart.setNote(index, ($event.target as HTMLInputElement).value)">
                </div>
            </div>
            <div class="mt-5 flex items-center justify-between border-t border-slate-700 pt-4 text-lg font-bold"><span>Total</span><span>{{ cart.subtotal.toLocaleString() }} UZS</span></div>
            <p v-if="error" class="mt-3 text-sm text-red-300">{{ error }}</p>
            <button class="mt-4 min-h-14 w-full rounded-xl bg-amber-400 px-5 font-bold text-slate-950 disabled:opacity-50" :disabled="busy || cart.items.length === 0" type="button" @click="saveOrder">
                {{ busy ? 'Saving…' : 'Save order' }}
            </button>
        </aside>
    </section>
</template>
