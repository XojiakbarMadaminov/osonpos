<script setup lang="ts">
import { computed, ref } from 'vue';
import { apiService } from '../services/api';
import { printerService } from '../services/printer';
import { ReceiptPrintService } from '../services/receipt-print';
import { useOrderStore } from '../stores/order';

const emit = defineEmits<{ navigate: [page: string] }>();
const order = useOrderStore();
const method = ref('CASH');
const amount = ref(order.current?.balance_due ?? 0);
const busy = ref(false);
const message = ref('');
const canPay = computed(() => order.current !== null && (order.current.balance_due === 0 || amount.value > 0));

async function pay(): Promise<void> {
    if (!order.current) return;
    busy.value = true;
    message.value = '';
    try {
        const balanceDue = order.current.balance_due;
        if (balanceDue > 0) {
            await apiService.createPayment(order.current.id, method.value, amount.value);
        }
        if (amount.value >= balanceDue) {
            const receipt = new ReceiptPrintService(apiService, printerService);
            await apiService.completeOrder(order.current.id);
            try {
                await receipt.printCompleted(order.current.id);
            } catch {
                // The saved payment and completed order must not depend on printing.
            }
            order.reset();
            emit('navigate', 'orders');
        } else {
            message.value = 'Partial payment saved.';
        }
    } catch (exception) {
        message.value = exception instanceof Error
            ? exception.message
            : 'Payment could not be completed.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section class="mx-auto max-w-xl rounded-xl border border-slate-800 bg-slate-900 p-6">
        <h2 class="text-xl font-semibold">Payment</h2>
        <p v-if="order.current" class="mt-2 text-slate-400">{{ order.current.display_number }} · {{ order.current.total.toLocaleString() }} UZS</p>
        <p v-else class="mt-4 text-slate-400">Choose or create an order first.</p>
        <div v-if="order.current" class="mt-6 space-y-5">
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                <button v-for="item in ['CASH', 'CARD', 'CLICK', 'PAYME', 'OTHER']" :key="item" class="min-h-12 rounded-lg border text-xs" :class="method === item ? 'border-amber-400 text-amber-300' : 'border-slate-700'" type="button" @click="method = item">{{ item }}</button>
            </div>
            <input v-if="order.current.balance_due > 0" v-model.number="amount" class="min-h-14 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 text-xl" min="1" type="number">
            <button class="min-h-14 w-full rounded-xl bg-amber-400 font-bold text-slate-950 disabled:opacity-50" :disabled="busy || !canPay" type="button" @click="pay">{{ busy ? 'Processing…' : order.current.balance_due > 0 ? 'Take payment and close' : 'Close order' }}</button>
            <p v-if="message" class="text-sm text-slate-300">{{ message }}</p>
            <button class="min-h-12 w-full rounded-lg border border-slate-700" type="button" @click="emit('navigate', 'pos')">Back to POS</button>
        </div>
    </section>
</template>
