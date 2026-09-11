<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { apiService, type CreatedOrder } from '../services/api';
import { KitchenPrintService } from '../services/kitchen-print';
import { countOrdersByStatus, filterOrdersByStatus, orderStatusFilters, type OrderStatusFilter } from '../services/order-filter';
import { printerService } from '../services/printer';
import { ReceiptPrintService } from '../services/receipt-print';
import { useAuthStore } from '../stores/auth';
import { useCartStore } from '../stores/cart';
import { useOrderStore } from '../stores/order';

const emit = defineEmits<{ navigate: [page: string] }>();
const orders = ref<CreatedOrder[]>([]);
const error = ref('');
const busyOrderId = ref('');
const selectedStatus = ref<OrderStatusFilter>('OPEN');
const auth = useAuthStore();
const cart = useCartStore();
const orderStore = useOrderStore();
const filteredOrders = computed(() => filterOrdersByStatus(orders.value, selectedStatus.value));

function addProducts(order: CreatedOrder): void {
    cart.clear();
    orderStore.openExisting(order);
    emit('navigate', 'pos');
}

function openPayment(order: CreatedOrder): void {
    cart.clear();
    orderStore.openExisting(order);
    emit('navigate', 'payment');
}

async function printKitchen(order: CreatedOrder, reprint = false): Promise<void> {
    busyOrderId.value = order.id;
    error.value = '';
    try {
        const service = new KitchenPrintService(apiService, printerService);
        if (reprint) await service.reprint(order.id);
        else await service.send(order.id);
    } catch {
        error.value = 'Kitchen printing failed. The order is still saved; reconnect QZ Tray and retry.';
    } finally {
        busyOrderId.value = '';
    }
}

async function reprintReceipt(order: CreatedOrder): Promise<void> {
    busyOrderId.value = order.id;
    error.value = '';
    try {
        await new ReceiptPrintService(apiService, printerService).reprint(order.id);
    } catch {
        error.value = 'Receipt printing failed. The payment remains saved; reconnect and retry.';
    } finally {
        busyOrderId.value = '';
    }
}

onMounted(async () => {
    try {
        orders.value = await apiService.orders();
    } catch {
        error.value = 'Orders could not be loaded.';
    }
});
</script>

<template>
    <section>
        <h2 class="text-xl font-semibold">Orders</h2>
        <div class="mt-4 flex flex-wrap gap-2" aria-label="Filter orders by status" role="tablist">
            <button
                v-for="status in orderStatusFilters"
                :key="status.value"
                class="min-h-10 rounded-lg border px-4 text-sm font-medium transition-colors"
                :class="selectedStatus === status.value ? 'border-amber-400 bg-amber-400 text-slate-950' : 'border-slate-700 bg-slate-900 text-slate-200 hover:border-slate-500'"
                :aria-selected="selectedStatus === status.value"
                role="tab"
                type="button"
                @click="selectedStatus = status.value"
            >
                {{ status.label }}
                <span class="ml-1 opacity-70">{{ countOrdersByStatus(orders, status.value) }}</span>
            </button>
        </div>
        <p v-if="error" class="mt-4 text-red-300">{{ error }}</p>
        <p v-else-if="filteredOrders.length === 0" class="mt-5 rounded-xl border border-dashed border-slate-700 p-6 text-center text-slate-400">No orders with this status.</p>
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="order in filteredOrders" :key="order.id" class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex justify-between"><strong>{{ order.display_number }}</strong><span class="text-sm text-amber-300">{{ order.status }}</span></div>
                <p class="mt-2 text-sm text-slate-400">
                    {{ order.type.replaceAll('_', ' ') }}
                    <span v-if="order.type === 'DINE_IN'" class="ml-2 rounded-md bg-amber-400/15 px-2 py-1 font-medium text-amber-300">Table {{ order.table?.number ?? order.table_id }}</span>
                </p>
                <p class="mt-4 text-lg font-semibold">{{ order.total.toLocaleString() }} UZS</p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button v-if="order.status === 'OPEN'" class="col-span-2 min-h-11 rounded-lg bg-amber-400 text-sm font-semibold text-slate-950" type="button" @click="addProducts(order)">Add products</button>
                    <button v-if="order.status === 'OPEN' && auth.can('payments.create')" class="col-span-2 min-h-11 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-950" type="button" @click="openPayment(order)">{{ order.balance_due > 0 ? 'Payment / Close' : 'Close order' }}</button>
                    <button v-if="order.status === 'OPEN'" class="min-h-11 rounded-lg border border-slate-700 text-sm" :disabled="busyOrderId === order.id" type="button" @click="printKitchen(order)">Send kitchen</button>
                    <button v-if="auth.can('orders.reprint')" class="min-h-11 rounded-lg border border-slate-700 text-sm" :disabled="busyOrderId === order.id" type="button" @click="printKitchen(order, true)">Kitchen reprint</button>
                    <button v-if="auth.can('orders.reprint') && order.payment_status === 'PAID'" class="col-span-2 min-h-11 rounded-lg border border-slate-700 text-sm" :disabled="busyOrderId === order.id" type="button" @click="reprintReceipt(order)">Receipt reprint</button>
                </div>
            </article>
        </div>
    </section>
</template>
