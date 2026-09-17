<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import CustomerPicker from '../components/customers/CustomerPicker.vue';
import type { CustomerSummary } from '../services/api';
import { apiService } from '../services/api';
import { canManageCustomerAtPayment } from '../services/customer-flow';
import {
    complementaryPaymentAmount,
    normalizedPaymentAmount,
    type PosPaymentMode,
    validMixedPayment,
} from '../services/payment-flow';
import { printerService } from '../services/printer';
import { ReceiptPrintService } from '../services/receipt-print';
import { useOrderStore } from '../stores/order';
import { useShiftStore } from '../stores/shift';
import { formatMoney } from '../utils/money';
import { generateUlid } from '../utils/ulid';

const emit = defineEmits<{ navigate: [page: string] }>();
const order = useOrderStore();
const shift = useShiftStore();
const method = ref<PosPaymentMode>('CASH');
const paymentMethods = [
    { value: 'CASH' as const, label: 'Naqd' },
    { value: 'CARD' as const, label: 'Karta' },
    { value: 'MIXED' as const, label: 'Naqd + Karta' },
] satisfies Array<{ value: PosPaymentMode; label: string }>;
const amount = ref(order.current?.balance_due ?? 0);
const cashAmount = ref(order.current?.balance_due ?? 0);
const cardAmount = ref(0);
const cashPaymentId = ref(generateUlid());
const cardPaymentId = ref(generateUlid());
const busy = ref(false);
const message = ref('');
const canPay = computed(() => shift.loaded
    && shift.current !== null
    && order.current !== null
    && (order.current.balance_due === 0
        || (method.value === 'MIXED'
            ? validMixedPayment(order.current.balance_due, cashAmount.value, cardAmount.value)
            : amount.value > 0 && amount.value <= order.current.balance_due)));

function chooseMethod(nextMethod: PosPaymentMode): void {
    method.value = nextMethod;
    const balance = order.current?.balance_due ?? 0;
    amount.value = balance;
    cashAmount.value = balance;
    cardAmount.value = 0;
    cashPaymentId.value = generateUlid();
    cardPaymentId.value = generateUlid();
}

function updateCashAmount(value: string): void {
    const balance = order.current?.balance_due ?? 0;
    cashAmount.value = normalizedPaymentAmount(balance, Number(value));
    cardAmount.value = complementaryPaymentAmount(balance, cashAmount.value);
}

function updateCardAmount(value: string): void {
    const balance = order.current?.balance_due ?? 0;
    cardAmount.value = normalizedPaymentAmount(balance, Number(value));
    cashAmount.value = complementaryPaymentAmount(balance, cardAmount.value);
}

async function pay(): Promise<void> {
    if (!order.current) return;
    if (!shift.current) {
        message.value = 'To‘lovni qabul qilish uchun avval smenani oching.';
        return;
    }
    busy.value = true;
    message.value = '';
    try {
        const balanceDue = order.current.balance_due;
        if (balanceDue > 0) {
            if (method.value === 'MIXED') {
                await apiService.createMixedPayment(
                    order.current.id,
                    cashPaymentId.value,
                    cashAmount.value,
                    cardPaymentId.value,
                    cardAmount.value,
                );
            } else {
                await apiService.createPayment(order.current.id, method.value, amount.value);
            }
        }
        if (method.value === 'MIXED' || amount.value >= balanceDue) {
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
            message.value = 'Qisman to‘lov saqlandi.';
        }
    } catch (exception) {
        message.value = exception instanceof Error
            ? exception.message
            : 'To‘lovni amalga oshirib bo‘lmadi.';
    } finally {
        busy.value = false;
    }
}

async function setCustomer(customer: CustomerSummary | null): Promise<void> {
    if (!order.current) return;
    busy.value = true;
    message.value = '';
    try {
        order.openExisting(customer
            ? await apiService.setOrderCustomer(order.current.id, customer.id)
            : await apiService.removeOrderCustomer(order.current.id));
        amount.value = order.current?.balance_due ?? 0;
        cashAmount.value = order.current?.balance_due ?? 0;
        cardAmount.value = 0;
    } catch (exception) {
        message.value = exception instanceof Error ? exception.message : 'Mijozni biriktirib bo‘lmadi.';
    } finally {
        busy.value = false;
    }
}

onMounted(() => shift.load(true));
</script>

<template>
    <section class="mx-auto max-w-xl rounded-xl border border-slate-800 bg-slate-900 p-6">
        <h2 class="text-xl font-semibold">To‘lov</h2>
        <p v-if="order.current" class="mt-2 text-slate-400">{{ order.current.display_number }} · {{ formatMoney(order.current.total) }} UZS</p>
        <p v-else class="mt-4 text-slate-400">Avval buyurtmani tanlang yoki yarating.</p>
        <div v-if="order.current" class="mt-6 space-y-5">
            <div v-if="shift.loaded && !shift.current" class="rounded-lg border border-amber-700 bg-amber-500/10 p-4 text-sm text-amber-200">
                <p>To‘lovni qabul qilish uchun avval smenani oching.</p>
                <button class="mt-3 font-semibold text-amber-300 underline" type="button" @click="emit('navigate', 'shift')">Smenaga o‘tish</button>
            </div>
            <p v-else-if="shift.error" class="rounded-lg border border-red-800 bg-red-500/10 p-4 text-sm text-red-200">{{ shift.error }}</p>
            <div class="grid grid-cols-3 gap-2">
                <button v-for="item in paymentMethods" :key="item.value" class="min-h-12 rounded-lg border text-xs" :class="method === item.value ? 'border-amber-400 text-amber-300' : 'border-slate-700'" type="button" @click="chooseMethod(item.value)">{{ item.label }}</button>
            </div>
            <div v-if="order.current.balance_due > 0 && method === 'MIXED'" class="grid gap-3 sm:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-300">
                    <span>Naqd summa</span>
                    <input :value="cashAmount" class="min-h-14 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 text-xl text-slate-100" min="0" :max="order.current.balance_due" step="1" type="number" @input="updateCashAmount(($event.target as HTMLInputElement).value)">
                </label>
                <label class="space-y-2 text-sm text-slate-300">
                    <span>Karta summasi</span>
                    <input :value="cardAmount" class="min-h-14 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 text-xl text-slate-100" min="0" :max="order.current.balance_due" step="1" type="number" @input="updateCardAmount(($event.target as HTMLInputElement).value)">
                </label>
            </div>
            <input v-else-if="order.current.balance_due > 0" v-model.number="amount" class="min-h-14 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 text-xl" min="1" :max="order.current.balance_due" step="1" type="number">
            <CustomerPicker
                v-if="canManageCustomerAtPayment(order.current.type)"
                add-label="+ Mijoz biriktirish"
                :disabled="busy"
                :model-value="order.selectedCustomer"
                @update:model-value="setCustomer"
            />
            <button class="min-h-14 w-full rounded-xl bg-amber-400 font-bold text-slate-950 disabled:opacity-50" :disabled="busy || !canPay" type="button" @click="pay">{{ busy ? 'Amalga oshirilmoqda…' : order.current.balance_due > 0 ? 'To‘lovni olish va yopish' : 'Buyurtmani yopish' }}</button>
            <p v-if="message" class="text-sm text-slate-300">{{ message }}</p>
            <button class="min-h-12 w-full rounded-lg border border-slate-700" type="button" @click="emit('navigate', 'pos')">POS’ga qaytish</button>
        </div>
    </section>
</template>
