<script setup lang="ts">
import { ref } from 'vue';
import OrderTableMoveModal from '../components/orders/OrderTableMoveModal.vue';
import { apiService } from '../services/api';
import type { CreatedOrder } from '../services/api';
import { useAuthStore } from '../stores/auth';
import { useCartStore } from '../stores/cart';
import { useContextStore } from '../stores/context';
import { useOrderStore } from '../stores/order';
import type { PosTable } from '../types/bootstrap';

defineProps<{ tables: PosTable[] }>();
const emit = defineEmits<{ navigate: [page: string] }>();
const auth = useAuthStore();
const order = useOrderStore();
const cart = useCartStore();
const context = useContextStore();
const busyTableId = ref<number | null>(null);
const error = ref('');
const movingOrder = ref<CreatedOrder | null>(null);
const moveError = ref('');

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
        error.value = 'Stoldagi ochiq buyurtmani yuklab bo‘lmadi.';
    } finally {
        busyTableId.value = null;
    }
}

async function openMove(table: PosTable): Promise<void> {
    if (!table.open_order_id) return;

    busyTableId.value = table.id;
    error.value = '';
    moveError.value = '';
    try {
        const [existingOrder, bootstrap] = await Promise.all([
            apiService.order(table.open_order_id),
            apiService.bootstrap(),
        ]);
        context.hydrate(bootstrap);
        movingOrder.value = existingOrder;
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Buyurtmani ko‘chirish uchun ma’lumotlarni yuklab bo‘lmadi.';
    } finally {
        busyTableId.value = null;
    }
}

async function moveTable(tableId: number): Promise<void> {
    if (!movingOrder.value) return;
    const currentTableId = movingOrder.value.table_id;
    busyTableId.value = currentTableId;
    moveError.value = '';
    try {
        await apiService.moveOrderTable(movingOrder.value.id, tableId);
        context.hydrate(await apiService.bootstrap());
        movingOrder.value = null;
    } catch (exception) {
        moveError.value = exception instanceof Error ? exception.message : 'Buyurtmani boshqa stolga ko‘chirib bo‘lmadi.';
    } finally {
        busyTableId.value = null;
    }
}
</script>

<template>
    <section>
        <h2 class="text-xl font-semibold">Stollar</h2>
        <p v-if="error" class="mt-4 text-red-300">{{ error }}</p>
        <div class="mt-5 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
            <template v-for="table in tables" :key="table.id">
                <article v-if="table.is_occupied" class="rounded-xl border border-red-700 bg-red-500/10 p-4">
                    <span class="block text-lg font-semibold">{{ table.name }}</span>
                    <span class="mt-2 block text-sm text-red-200">{{ busyTableId === table.id ? 'Ochilmoqda…' : 'Band · ochiq buyurtma' }}</span>
                    <div class="mt-4 grid gap-2">
                        <button v-if="auth.can('orders.update')" class="min-h-10 rounded-lg bg-amber-400 px-3 text-sm font-semibold text-slate-950 disabled:opacity-60" :disabled="busyTableId === table.id" type="button" @click="openTableOrder(table, 'pos')">Mahsulot qo‘shish</button>
                        <button v-if="auth.can('orders.update')" class="min-h-10 rounded-lg border border-amber-400 px-3 text-sm font-semibold text-amber-300 disabled:opacity-60" :disabled="busyTableId === table.id" type="button" @click="openMove(table)">Stolni ko‘chirish</button>
                        <button v-if="auth.can('payments.create')" class="min-h-10 rounded-lg bg-emerald-500 px-3 text-sm font-semibold text-slate-950 disabled:opacity-60" :disabled="busyTableId === table.id" type="button" @click="openTableOrder(table, 'payment')">To‘lov / yopish</button>
                    </div>
                </article>
                <button v-else class="min-h-28 rounded-xl border border-emerald-700 bg-emerald-500/10 p-4 text-left" type="button" @click="selectFreeTable(table)">
                    <span class="block text-lg font-semibold">{{ table.name }}</span>
                    <span class="mt-2 block text-sm">Bo‘sh</span>
                </button>
            </template>
        </div>
        <OrderTableMoveModal
            v-if="movingOrder && context.bootstrap"
            :order="movingOrder"
            :tables="context.bootstrap.tables"
            :busy="busyTableId === movingOrder.table_id"
            :error="moveError"
            @close="movingOrder = null"
            @submit="moveTable"
        />
    </section>
</template>
