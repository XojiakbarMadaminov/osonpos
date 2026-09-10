<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { apiService, type ShiftSummary } from '../services/api';

const shift = ref<ShiftSummary | null>(null);
const cash = ref(0);
const busy = ref(false);
const error = ref('');

async function load(): Promise<void> {
    try {
        shift.value = await apiService.currentShift();
    } catch {
        error.value = 'Shift status unavailable.';
    }
}

async function toggle(): Promise<void> {
    busy.value = true;
    error.value = '';

    try {
        shift.value = shift.value
            ? await apiService.closeShift(shift.value.id, cash.value)
            : await apiService.openShift(cash.value);
        if (shift.value.status === 'CLOSED') shift.value = null;
        cash.value = 0;
    } catch {
        error.value = 'Shift could not be updated.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-wrap items-center justify-end gap-2">
        <span v-if="shift" class="rounded-full bg-emerald-500/15 px-3 py-1 text-sm text-emerald-300">Shift open</span>
        <input v-model.number="cash" class="min-h-10 w-32 rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm" min="0" type="number" :aria-label="shift ? 'Closing cash' : 'Opening cash'">
        <button class="min-h-10 rounded-lg border border-slate-700 px-4 text-sm font-medium" :disabled="busy" type="button" @click="toggle">
            {{ shift ? 'Close shift' : 'Open shift' }}
        </button>
        <span v-if="error" class="w-full text-right text-xs text-red-300">{{ error }}</span>
    </div>
</template>
