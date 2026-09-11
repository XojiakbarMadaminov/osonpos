<script setup lang="ts">
import { ref } from 'vue';
import { apiService } from '../services/api';
import { useAuthStore } from '../stores/auth';
import { useCartStore } from '../stores/cart';
import { useOrderStore } from '../stores/order';
import type { PosTable } from '../types/bootstrap';

defineProps<{ tables: PosTable[] }>();
const emit = defineEmits<{ navigate: [page: string] }>();
const auth = useAuthStore();
const order = useOrderStore();
const cart = useCartStore();
const busyTableId = ref<number | null>(null);
const error = ref('');

function selectFreeTable(table: PosTable): void {
    cart.clear();
    order.selectTable(table.id);
    emit('navigate', 'pos');
}

async function openTableOrder(table: PosTable, destination: 'pos' | 'payment'): Promise<void> {
    if (!table.open_order_id) return;

    busyTableId.value = table.id;
    error.value = '';
    try {
        const existingOrder = await apiService.order(table.open_order_id);
        cart.clear();
        order.openExisting(existingOrder);
        emit('navigate', destination);
    } catch {
        error.value = 'The open table order could not be loaded.';
    } finally {
        busyTableId.value = null;
    }
}
</script>

<template>
    <section>
        <h2 class="text-xl font-semibold">Tables</h2>
        <p v-if="error" class="mt-4 text-red-300">{{ error }}</p>
        <div class="mt-5 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
            <template v-for="table in tables" :key="table.id">
                <article v-if="table.is_occupied" class="rounded-xl border border-red-700 bg-red-500/10 p-4">
                    <span class="block text-lg font-semibold">{{ table.name }}</span>
                    <span class="mt-2 block text-sm text-red-200">{{ busyTableId === table.id ? 'Opening…' : 'Occupied · open order' }}</span>
                    <div class="mt-4 grid gap-2">
                        <button v-if="auth.can('orders.update')" class="min-h-10 rounded-lg bg-amber-400 px-3 text-sm font-semibold text-slate-950 disabled:opacity-60" :disabled="busyTableId === table.id" type="button" @click="openTableOrder(table, 'pos')">Add products</button>
                        <button v-if="auth.can('payments.create')" class="min-h-10 rounded-lg bg-emerald-500 px-3 text-sm font-semibold text-slate-950 disabled:opacity-60" :disabled="busyTableId === table.id" type="button" @click="openTableOrder(table, 'payment')">Payment / Close</button>
                    </div>
                </article>
                <button v-else class="min-h-28 rounded-xl border border-emerald-700 bg-emerald-500/10 p-4 text-left" type="button" @click="selectFreeTable(table)">
                    <span class="block text-lg font-semibold">{{ table.name }}</span>
                    <span class="mt-2 block text-sm">Free</span>
                </button>
            </template>
        </div>
    </section>
</template>
