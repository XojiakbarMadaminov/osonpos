<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import OrderItemRemovalModal from '../components/orders/OrderItemRemovalModal.vue';
import OrderItemsViewModal from '../components/orders/OrderItemsViewModal.vue';
import { apiService, type CreatedOrder } from '../services/api';
import { KitchenPrintService } from '../services/kitchen-print';
import { KitchenRemovalPrintService } from '../services/kitchen-removal-print';
import { countOrdersByStatus, filterOrdersByStatus, orderStatusFilters, orderStatusLabel, orderTypeLabel, type OrderStatusFilter } from '../services/order-filter';
import { printerService } from '../services/printer';
import { ReceiptPrintService } from '../services/receipt-print';
import { useAuthStore } from '../stores/auth';
import { useCartStore } from '../stores/cart';
import { useOrderStore } from '../stores/order';
import { formatMoney } from '../utils/money';
import { generateUlid } from '../utils/ulid';

const emit = defineEmits<{ navigate: [page: string] }>();
const orders = ref<CreatedOrder[]>([]);
const error = ref('');
const busyOrderId = ref('');
const selectedStatus = ref<OrderStatusFilter>('OPEN');
const removalOrder = ref<CreatedOrder | null>(null);
const viewOrder = ref<CreatedOrder | null>(null);
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

async function openRemoval(order: CreatedOrder): Promise<void> {
    busyOrderId.value = order.id;
    error.value = '';
    try {
        removalOrder.value = await apiService.order(order.id);
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Buyurtma mahsulotlarini yuklab bo‘lmadi.';
    } finally {
        busyOrderId.value = '';
    }
}

async function openView(order: CreatedOrder): Promise<void> {
    busyOrderId.value = order.id;
    error.value = '';
    try {
        viewOrder.value = await apiService.order(order.id);
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Buyurtma tafsilotlarini yuklab bo‘lmadi.';
    } finally {
        busyOrderId.value = '';
    }
}

function replaceOrder(updated: CreatedOrder): void {
    const index = orders.value.findIndex((order) => order.id === updated.id);
    if (index >= 0) orders.value[index] = updated;
}

async function printKitchenRemovals(order: CreatedOrder): Promise<void> {
    busyOrderId.value = order.id;
    error.value = '';
    try {
        await new KitchenRemovalPrintService(apiService, printerService).send(order.id);
        replaceOrder(await apiService.order(order.id));
    } catch {
        error.value = 'Mahsulot ayirish cheki oshxonaga chiqmadi. Ayirish saqlandi; QZ Tray’ni qayta ulang va takrorlang.';
    } finally {
        busyOrderId.value = '';
    }
}

async function removeItems(items: Array<{ order_item_id: string; quantity: number }>): Promise<void> {
    if (!removalOrder.value) return;
    const orderId = removalOrder.value.id;
    busyOrderId.value = orderId;
    error.value = '';
    try {
        const updated = await apiService.removeOrderItems(orderId, items.map((item) => ({
            id: generateUlid(),
            ...item,
        })));
        replaceOrder(updated);
        removalOrder.value = null;
        if (updated.pending_item_removals_count > 0) await printKitchenRemovals(updated);
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Mahsulotlarni ayirib bo‘lmadi.';
    } finally {
        busyOrderId.value = '';
    }
}

async function printKitchen(order: CreatedOrder, reprint = false): Promise<void> {
    busyOrderId.value = order.id;
    error.value = '';
    try {
        const service = new KitchenPrintService(apiService, printerService);
        if (reprint) await service.reprint(order.id);
        else {
            await service.send(order.id);
            Object.assign(order, await apiService.order(order.id));
        }
    } catch {
        error.value = 'Oshxona chekini chiqarib bo‘lmadi. Buyurtma saqlandi; QZ Tray’ni qayta ulang va takrorlang.';
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
        error.value = 'Mijoz chekini chiqarib bo‘lmadi. To‘lov saqlandi; printerni qayta ulang va takrorlang.';
    } finally {
        busyOrderId.value = '';
    }
}

onMounted(async () => {
    try {
        orders.value = await apiService.orders();
    } catch {
        error.value = 'Buyurtmalarni yuklab bo‘lmadi.';
    }
});
</script>

<template>
    <section>
        <h2 class="text-xl font-semibold">Buyurtmalar</h2>
        <div class="mt-4 flex flex-wrap gap-2" aria-label="Buyurtmalarni holati bo‘yicha saralash" role="tablist">
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
        <p v-else-if="filteredOrders.length === 0" class="mt-5 rounded-xl border border-dashed border-slate-700 p-6 text-center text-slate-400">Bu holatda buyurtmalar yo‘q.</p>
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="order in filteredOrders" :key="order.id" class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex justify-between"><strong>{{ order.display_number }}</strong><span class="text-sm text-amber-300">{{ orderStatusLabel(order.status) }}</span></div>
                <p class="mt-2 text-sm text-slate-400">
                    {{ orderTypeLabel(order.type) }}
                    <span v-if="order.type === 'DINE_IN'" class="ml-2 rounded-md bg-amber-400/15 px-2 py-1 font-medium text-amber-300">Stol {{ order.table?.number ?? order.table_id }}</span>
                </p>
                <p class="mt-4 text-lg font-semibold">{{ formatMoney(order.total) }} UZS</p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <!-- Actions for OPEN orders -->
                    <button v-if="order.status === 'OPEN'" class="min-h-11 rounded-lg bg-amber-400 text-sm font-semibold text-slate-950" type="button" @click="addProducts(order)">Mahsulot qo‘shish</button>
                    <button v-if="order.status === 'OPEN'" class="min-h-11 rounded-lg border border-red-400 text-sm font-semibold text-red-300" :disabled="busyOrderId === order.id" type="button" @click="openRemoval(order)">Mahsulot ayirish</button>
                    <button v-if="order.status === 'OPEN' && auth.can('payments.create')" class="col-span-2 min-h-11 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-950" type="button" @click="openPayment(order)">{{ order.balance_due > 0 ? 'To‘lov / yopish' : 'Buyurtmani yopish' }}</button>
                    <button v-if="order.pending_item_removals_count > 0" class="col-span-2 min-h-11 rounded-lg border border-red-400 text-sm text-red-300" :disabled="busyOrderId === order.id" type="button" @click="printKitchenRemovals(order)">Ayirilganlarni chop etish ({{ order.pending_item_removals_count }})</button>
                    <button v-if="order.status === 'OPEN' && order.unprinted_items_count > 0" class="min-h-11 rounded-lg border border-slate-700 text-sm" :class="{ 'col-span-2': !auth.can('orders.reprint') || order.payment_status === 'PAID' }" :disabled="busyOrderId === order.id" type="button" @click="printKitchen(order)">Chiqmaganlari ({{ order.unprinted_items_count }})</button>
                    
                    <!-- NEW: View Items for closed/completed orders -->
                    <button v-if="order.status !== 'OPEN'" class="col-span-2 min-h-11 rounded-lg bg-slate-800 hover:bg-slate-700 transition-colors text-sm font-semibold text-slate-200" :disabled="busyOrderId === order.id" type="button" @click="openView(order)">Buyurtma mahsulotlari</button>

                    <!-- Reprint buttons (better layout for all orders) -->
                    <button v-if="auth.can('orders.reprint')" class="min-h-11 rounded-lg border border-slate-700 text-sm hover:bg-slate-800 transition-colors" :class="{ 'col-span-2': order.payment_status !== 'PAID' && !(order.status === 'OPEN' && order.unprinted_items_count > 0) }" :disabled="busyOrderId === order.id" type="button" @click="printKitchen(order, true)">Oshxona cheki</button>
                    <button v-if="auth.can('orders.reprint') && order.payment_status === 'PAID'" class="min-h-11 rounded-lg border border-slate-700 text-sm hover:bg-slate-800 transition-colors" :disabled="busyOrderId === order.id" type="button" @click="reprintReceipt(order)">Mijoz cheki</button>
                </div>
            </article>
        </div>
        <OrderItemRemovalModal
            v-if="removalOrder"
            :order="removalOrder"
            :busy="busyOrderId === removalOrder.id"
            @close="removalOrder = null"
            @submit="removeItems"
        />
        <OrderItemsViewModal
            v-if="viewOrder"
            :order="viewOrder"
            @close="viewOrder = null"
        />
    </section>
</template>
