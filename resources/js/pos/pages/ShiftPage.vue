<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useContextStore } from '../stores/context';
import { useShiftStore } from '../stores/shift';

const shift = useShiftStore();
const context = useContextStore();
const cash = ref(0);
const actionLabel = computed(() => shift.current ? 'Smenani yopish' : 'Smenani ochish');
const closingDifference = computed(() => shift.current ? cash.value - shift.current.expected_cash : null);

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('uz-UZ', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function paymentMethodLabel(method: string): string {
    return {
        CASH: 'Naqd',
        CARD: 'Karta',
        CLICK: 'Click',
        PAYME: 'Payme',
        OTHER: 'Boshqa',
    }[method] ?? method;
}

async function submit(): Promise<void> {
    if (shift.current) {
        await shift.close(cash.value);
    } else {
        await shift.open(cash.value);
    }

    if (!shift.error) cash.value = 0;
}

onMounted(() => shift.load());
</script>

<template>
    <section class="mx-auto max-w-4xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">Kassir smenasi</h2>
                <p class="mt-1 text-sm text-slate-400">{{ context.bootstrap?.user.name }} · {{ context.bootstrap?.device.name }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-medium" :class="shift.current ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-300'">
                {{ shift.current ? 'OCHIQ' : 'FAOL SMENA YO‘Q' }}
            </span>
        </div>

        <div v-if="shift.current" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Boshlang‘ich naqd pul</p>
                <p class="mt-2 text-2xl font-semibold">{{ shift.current.opening_cash.toLocaleString() }} UZS</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Ochilgan vaqt</p>
                <p class="mt-2 text-lg font-semibold">{{ formatDate(shift.current.opened_at) }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Naqd to‘lovlar</p>
                <p class="mt-2 text-2xl font-semibold">{{ shift.current.cash_payments_total.toLocaleString() }} UZS</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Kutilayotgan naqd pul</p>
                <p class="mt-2 text-2xl font-semibold text-amber-300">{{ shift.current.expected_cash.toLocaleString() }} UZS</p>
            </div>
        </div>

        <div v-if="shift.current" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div v-for="(total, method) in shift.current.payment_totals" :key="method" class="rounded-lg border border-slate-800 bg-slate-900 p-3">
                <p class="text-xs text-slate-400">{{ paymentMethodLabel(method) }}</p>
                <p class="mt-1 font-medium">{{ total.toLocaleString() }} UZS</p>
            </div>
        </div>

        <form class="mt-5 rounded-xl border border-slate-800 bg-slate-900 p-5" @submit.prevent="submit">
            <label class="block font-medium" for="shift-cash">{{ shift.current ? 'Yopilishdagi naqd pul' : 'Boshlang‘ich naqd pul' }}</label>
            <p class="mt-1 text-sm text-slate-400">
                {{ shift.current ? 'Yopishdan oldin kassadagi haqiqiy naqd pulni sanang.' : 'Savdo boshlanishidan oldin kassadagi naqd pulni kiriting.' }}
            </p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <input id="shift-cash" v-model.number="cash" class="min-h-12 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-4 text-lg" min="0" required type="number">
                <button class="min-h-12 rounded-lg bg-amber-400 px-6 font-semibold text-slate-950 disabled:opacity-60" :disabled="shift.busy" type="submit">
                    {{ shift.busy ? 'Saqlanmoqda…' : actionLabel }}
                </button>
            </div>
            <div v-if="shift.current" class="mt-4 flex items-center justify-between rounded-lg bg-slate-950 px-4 py-3">
                <span class="text-sm text-slate-400">Hisoblangan farq</span>
                <span class="font-semibold" :class="closingDifference === 0 ? 'text-emerald-300' : 'text-amber-300'">
                    {{ closingDifference?.toLocaleString() }} UZS
                </span>
            </div>
            <p v-if="shift.error" class="mt-3 text-sm text-red-300">{{ shift.error }}</p>
        </form>

        <div class="mt-5 rounded-xl border border-slate-800 bg-slate-900 p-5 text-sm text-slate-300">
            <h3 class="font-semibold text-slate-100">Ishlash tartibi</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Boshlang‘ich naqd pulni kiriting va smenani oching.</li>
                <li>Naqd to‘lovlar faqat smena ochiq paytda qabul qilinadi.</li>
                <li>Karta va boshqa to‘lovlar shu smenada hisoblanadi, ammo kutilayotgan naqd pulni oshirmaydi.</li>
                <li>Smenani yopishdan oldin ushbu qurilmadagi ochiq buyurtmalarni yoping yoki bekor qiling.</li>
                <li>Oxirida kassadagi pulni sanang, yopilish summasini kiriting, farqni tekshiring va smenani yoping.</li>
            </ol>
        </div>
    </section>
</template>
