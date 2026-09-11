<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useContextStore } from '../stores/context';
import { useShiftStore } from '../stores/shift';

const shift = useShiftStore();
const context = useContextStore();
const cash = ref(0);
const actionLabel = computed(() => shift.current ? 'Close shift' : 'Open shift');
const closingDifference = computed(() => shift.current ? cash.value - shift.current.expected_cash : null);

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
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
                <h2 class="text-xl font-semibold">Cashier shift</h2>
                <p class="mt-1 text-sm text-slate-400">{{ context.bootstrap?.user.name }} · {{ context.bootstrap?.device.name }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-medium" :class="shift.current ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-300'">
                {{ shift.current ? 'OPEN' : 'NO ACTIVE SHIFT' }}
            </span>
        </div>

        <div v-if="shift.current" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Opening cash</p>
                <p class="mt-2 text-2xl font-semibold">{{ shift.current.opening_cash.toLocaleString() }} UZS</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Opened at</p>
                <p class="mt-2 text-lg font-semibold">{{ formatDate(shift.current.opened_at) }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Cash payments</p>
                <p class="mt-2 text-2xl font-semibold">{{ shift.current.cash_payments_total.toLocaleString() }} UZS</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <p class="text-sm text-slate-400">Expected cash</p>
                <p class="mt-2 text-2xl font-semibold text-amber-300">{{ shift.current.expected_cash.toLocaleString() }} UZS</p>
            </div>
        </div>

        <div v-if="shift.current" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div v-for="(total, method) in shift.current.payment_totals" :key="method" class="rounded-lg border border-slate-800 bg-slate-900 p-3">
                <p class="text-xs text-slate-400">{{ method }}</p>
                <p class="mt-1 font-medium">{{ total.toLocaleString() }} UZS</p>
            </div>
        </div>

        <form class="mt-5 rounded-xl border border-slate-800 bg-slate-900 p-5" @submit.prevent="submit">
            <label class="block font-medium" for="shift-cash">{{ shift.current ? 'Closing cash' : 'Opening cash' }}</label>
            <p class="mt-1 text-sm text-slate-400">
                {{ shift.current ? 'Count the actual cash currently in the register before closing.' : 'Enter the cash already present in the register before sales begin.' }}
            </p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <input id="shift-cash" v-model.number="cash" class="min-h-12 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-4 text-lg" min="0" required type="number">
                <button class="min-h-12 rounded-lg bg-amber-400 px-6 font-semibold text-slate-950 disabled:opacity-60" :disabled="shift.busy" type="submit">
                    {{ shift.busy ? 'Saving…' : actionLabel }}
                </button>
            </div>
            <div v-if="shift.current" class="mt-4 flex items-center justify-between rounded-lg bg-slate-950 px-4 py-3">
                <span class="text-sm text-slate-400">Counted difference</span>
                <span class="font-semibold" :class="closingDifference === 0 ? 'text-emerald-300' : 'text-amber-300'">
                    {{ closingDifference?.toLocaleString() }} UZS
                </span>
            </div>
            <p v-if="shift.error" class="mt-3 text-sm text-red-300">{{ shift.error }}</p>
        </form>

        <div class="mt-5 rounded-xl border border-slate-800 bg-slate-900 p-5 text-sm text-slate-300">
            <h3 class="font-semibold text-slate-100">How it works</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Enter opening cash and open the shift.</li>
                <li>Cash payments are accepted only while your shift is open.</li>
                <li>Card and other payments are tracked in the same shift, but do not increase expected cash.</li>
                <li>Close or cancel this device's open orders before ending the shift.</li>
                <li>At the end, count the register cash, enter closing cash, review the difference, and close the shift.</li>
            </ol>
        </div>
    </section>
</template>
