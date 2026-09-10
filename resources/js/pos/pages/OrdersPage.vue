<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiService, type CreatedOrder } from '../services/api';
import { KitchenPrintService } from '../services/kitchen-print';
import { printerService } from '../services/printer';
import { ReceiptPrintService } from '../services/receipt-print';
import { useAuthStore } from '../stores/auth';

const orders = ref<CreatedOrder[]>([]);
const error = ref('');
const busyOrderId = ref('');
const auth = useAuthStore();

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
        <p v-if="error" class="mt-4 text-red-300">{{ error }}</p>
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="order in orders" :key="order.id" class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex justify-between"><strong>{{ order.display_number }}</strong><span class="text-sm text-amber-300">{{ order.status }}</span></div>
                <p class="mt-2 text-sm text-slate-400">{{ order.type.replaceAll('_', ' ') }}</p>
                <p class="mt-4 text-lg font-semibold">{{ order.total.toLocaleString() }} UZS</p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button v-if="order.status === 'OPEN'" class="min-h-11 rounded-lg border border-slate-700 text-sm" :disabled="busyOrderId === order.id" type="button" @click="printKitchen(order)">Send kitchen</button>
                    <button v-if="auth.can('orders.reprint')" class="min-h-11 rounded-lg border border-slate-700 text-sm" :disabled="busyOrderId === order.id" type="button" @click="printKitchen(order, true)">Kitchen reprint</button>
                    <button v-if="auth.can('orders.reprint') && order.payment_status === 'PAID'" class="col-span-2 min-h-11 rounded-lg border border-slate-700 text-sm" :disabled="busyOrderId === order.id" type="button" @click="reprintReceipt(order)">Receipt reprint</button>
                </div>
            </article>
        </div>
    </section>
</template>
