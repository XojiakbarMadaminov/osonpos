<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import CustomerPicker from '../components/customers/CustomerPicker.vue';
import type { CustomerSummary } from '../services/api';
import { apiService } from '../services/api';
import { canManageCustomerAtPayment } from '../services/customer-flow';
import { calculateDiscountAmount, discountValidationMessage, normalizeDiscountValue, type DiscountType } from '../services/discount';
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
const cashAmount = ref(order.current?.balance_due ?? 0);
const cardAmount = ref(0);
const cashPaymentId = ref(generateUlid());
const cardPaymentId = ref(generateUlid());
const discountType = ref<DiscountType>(order.current?.discount_type ?? 'PERCENTAGE');
const discountValue = ref(order.current?.discount_value ?? 0);
const busy = ref(false);
const message = ref('');
const discountError = computed(() => order.current
    ? discountValidationMessage(order.current.subtotal, discountType.value, discountValue.value)
    : '');
const previewDiscountAmount = computed(() => order.current
    ? calculateDiscountAmount(order.current.subtotal, discountType.value, discountValue.value)
    : 0);
const previewTotal = computed(() => order.current
    ? order.current.subtotal - previewDiscountAmount.value + order.current.delivery_fee
    : 0);
const previewBalance = computed(() => Math.max(0, previewTotal.value - (order.current?.paid_amount ?? 0)));
const canPay = computed(() => shift.loaded
    && shift.current !== null
    && order.current !== null
    && discountError.value === ''
    && (previewBalance.value === 0
        || (method.value === 'MIXED'
            ? validMixedPayment(previewBalance.value, cashAmount.value, cardAmount.value)
            : previewBalance.value > 0)));

function resetPaymentAmounts(balance: number): void {
    cashAmount.value = balance;
    cardAmount.value = 0;
    cashPaymentId.value = generateUlid();
    cardPaymentId.value = generateUlid();
}

function chooseMethod(nextMethod: PosPaymentMode): void {
    method.value = nextMethod;
    resetPaymentAmounts(previewBalance.value);
}

function updateCashAmount(value: string): void {
    const balance = previewBalance.value;
    cashAmount.value = normalizedPaymentAmount(balance, Number(value));
    cardAmount.value = complementaryPaymentAmount(balance, cashAmount.value);
}

function updateCardAmount(value: string): void {
    const balance = previewBalance.value;
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
        const normalizedDiscount = normalizeDiscountValue(discountValue.value);
        const updatedOrder = normalizedDiscount > 0
            ? await apiService.setOrderDiscount(order.current.id, discountType.value, normalizedDiscount)
            : await apiService.removeOrderDiscount(order.current.id);
        order.openExisting(updatedOrder);
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
                await apiService.createPayment(order.current.id, method.value, balanceDue);
            }
        }
        const receipt = new ReceiptPrintService(apiService, printerService);
        await apiService.completeOrder(order.current.id);
        try {
            await receipt.printCompleted(order.current.id);
        } catch {
            // The saved payment and completed order must not depend on printing.
        }
        order.reset();
        emit('navigate', 'orders');
    } catch (exception) {
        message.value = exception instanceof Error
            ? exception.message
            : 'To‘lovni amalga oshirib bo‘lmadi.';
    } finally {
        busy.value = false;
    }
}

function updateDiscountValue(value: string): void {
    discountValue.value = normalizeDiscountValue(value);
}

async function setCustomer(customer: CustomerSummary | null): Promise<void> {
    if (!order.current) return;
    busy.value = true;
    message.value = '';
    try {
        order.openExisting(customer
            ? await apiService.setOrderCustomer(order.current.id, customer.id)
            : await apiService.removeOrderCustomer(order.current.id));
        resetPaymentAmounts(previewBalance.value);
    } catch (exception) {
        message.value = exception instanceof Error ? exception.message : 'Mijozni biriktirib bo‘lmadi.';
    } finally {
        busy.value = false;
    }
}

onMounted(() => shift.load(true));

watch(previewBalance, (balance) => resetPaymentAmounts(balance));
</script>

<template>
    <section class="mx-auto max-w-xl rounded-xl border border-slate-800 bg-slate-900 p-6">
        <h2 class="text-xl font-semibold">To‘lov</h2>
        <p v-if="order.current" class="mt-2 text-slate-400">{{ order.current.display_number }} · {{ formatMoney(previewTotal) }} UZS</p>
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
            <div v-if="previewBalance > 0 && method === 'MIXED'" class="grid gap-3 sm:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-300">
                    <span>Naqd summa</span>
                    <input :value="cashAmount" class="min-h-14 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 text-xl text-slate-100" min="0" :max="previewBalance" step="1" type="number" @input="updateCashAmount(($event.target as HTMLInputElement).value)">
                </label>
                <label class="space-y-2 text-sm text-slate-300">
                    <span>Karta summasi</span>
                    <input :value="cardAmount" class="min-h-14 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 text-xl text-slate-100" min="0" :max="previewBalance" step="1" type="number" @input="updateCardAmount(($event.target as HTMLInputElement).value)">
                </label>
            </div>
            <CustomerPicker
                v-if="canManageCustomerAtPayment(order.current.type)"
                add-label="+ Mijoz biriktirish"
                :disabled="busy"
                :model-value="order.selectedCustomer"
                @update:model-value="setCustomer"
            />
            <div class="rounded-xl border border-slate-700 bg-slate-950/60 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-100">Chegirma</p>
                        <p class="text-xs text-slate-400">Ixtiyoriy</p>
                    </div>
                    <div class="grid grid-cols-2 rounded-lg bg-slate-900 p-1 text-sm">
                        <button class="min-h-9 rounded-md px-3" :class="discountType === 'PERCENTAGE' ? 'bg-amber-400 font-semibold text-slate-950' : 'text-slate-300'" :disabled="busy" type="button" @click="discountType = 'PERCENTAGE'">Foiz</button>
                        <button class="min-h-9 rounded-md px-3" :class="discountType === 'FIXED' ? 'bg-amber-400 font-semibold text-slate-950' : 'text-slate-300'" :disabled="busy" type="button" @click="discountType = 'FIXED'">Summa</button>
                    </div>
                </div>
                <label class="relative mt-3 block">
                    <span class="sr-only">Chegirma qiymati</span>
                    <input :value="discountValue || ''" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 pr-16 text-lg text-slate-100 focus:border-amber-400 focus:outline-none" min="0" :max="discountType === 'PERCENTAGE' ? 100 : order.current.subtotal" step="1" type="number" placeholder="0" :disabled="busy" @input="updateDiscountValue(($event.target as HTMLInputElement).value)">
                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-semibold text-slate-400">{{ discountType === 'PERCENTAGE' ? '%' : 'UZS' }}</span>
                </label>
                <p v-if="discountError" class="mt-2 text-sm text-red-300">{{ discountError }}</p>
                <div v-else class="mt-3 space-y-1.5 border-t border-slate-800 pt-3 text-sm">
                    <div class="flex justify-between text-slate-400"><span>Oraliq jami</span><span>{{ formatMoney(order.current.subtotal) }} UZS</span></div>
                    <div v-if="order.current.delivery_fee > 0" class="flex justify-between text-slate-400"><span>Yetkazib berish</span><span>{{ formatMoney(order.current.delivery_fee) }} UZS</span></div>
                    <div class="flex justify-between text-emerald-400"><span>Chegirma</span><span>− {{ formatMoney(previewDiscountAmount) }} UZS</span></div>
                    <div class="flex justify-between pt-1 text-base font-bold text-slate-100"><span>To‘lanadi</span><span>{{ formatMoney(previewTotal) }} UZS</span></div>
                </div>
            </div>
            <button class="min-h-14 w-full rounded-xl bg-amber-400 font-bold text-slate-950 disabled:opacity-50" :disabled="busy || !canPay" type="button" @click="pay">{{ busy ? 'Amalga oshirilmoqda…' : previewBalance > 0 ? 'To‘lovni olish va yopish' : 'Buyurtmani yopish' }}</button>
            <p v-if="message" class="text-sm text-slate-300">{{ message }}</p>
            <button class="min-h-12 w-full rounded-lg border border-slate-700" type="button" @click="emit('navigate', 'pos')">POS’ga qaytish</button>
        </div>
    </section>
</template>
