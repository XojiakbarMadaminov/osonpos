<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useShiftStore } from '../stores/shift';

const cash = ref(0);
const shift = useShiftStore();

async function toggle(): Promise<void> {
    if (shift.current) {
        await shift.close(cash.value);
    } else {
        await shift.open(cash.value);
    }
    if (!shift.error) {
        cash.value = 0;
    }
}

onMounted(() => shift.load());
</script>

<template>
    <div class="flex flex-wrap items-center justify-end gap-2">
        <span v-if="shift.current" class="rounded-full bg-emerald-500/15 px-3 py-1 text-sm text-emerald-300">Shift open</span>
        <input v-model.number="cash" class="min-h-10 w-32 rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm" min="0" type="number" :aria-label="shift.current ? 'Closing cash' : 'Opening cash'">
        <button class="min-h-10 rounded-lg border border-slate-700 px-4 text-sm font-medium" :disabled="shift.busy" type="button" @click="toggle">
            {{ shift.current ? 'Close shift' : 'Open shift' }}
        </button>
        <span v-if="shift.error" class="w-full text-right text-xs text-red-300">{{ shift.error }}</span>
    </div>
</template>
