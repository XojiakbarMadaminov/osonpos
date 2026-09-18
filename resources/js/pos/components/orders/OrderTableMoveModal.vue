<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { CreatedOrder } from '../../services/api';
import type { PosTable } from '../../types/bootstrap';

const props = defineProps<{
    order: CreatedOrder;
    tables: PosTable[];
    busy: boolean;
    error: string;
}>();
const emit = defineEmits<{
    close: [];
    submit: [tableId: number];
}>();
const selectedTableId = ref<number | null>(null);
const freeTables = computed(() => props.tables.filter((table) => !table.is_occupied && table.id !== props.order.table_id));

watch(() => props.order.id, () => {
    selectedTableId.value = null;
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-labelledby="move-table-title" @click.self="!busy && emit('close')">
        <section class="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 id="move-table-title" class="text-lg font-semibold">Stolni ko‘chirish</h3>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ order.display_number }} · {{ order.table?.name ?? `Stol ${order.table_id}` }}
                    </p>
                </div>
                <button class="rounded-lg px-3 py-2 text-slate-400 hover:bg-slate-800 hover:text-slate-100 disabled:opacity-50" :disabled="busy" type="button" aria-label="Yopish" @click="emit('close')">
                    ✕
                </button>
            </div>

            <p v-if="error" class="mt-4 rounded-lg border border-red-800 bg-red-500/10 p-3 text-sm text-red-200">{{ error }}</p>
            <p v-if="freeTables.length === 0" class="mt-5 rounded-lg border border-dashed border-slate-700 p-5 text-center text-sm text-slate-400">
                Ko‘chirish uchun bo‘sh stol yo‘q.
            </p>
            <div v-else class="mt-5 grid max-h-72 grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
                <button
                    v-for="table in freeTables"
                    :key="table.id"
                    class="min-h-20 rounded-xl border p-3 text-left transition-colors"
                    :class="selectedTableId === table.id ? 'border-amber-400 bg-amber-400/10 text-amber-300' : 'border-slate-700 hover:border-slate-500'"
                    :disabled="busy"
                    type="button"
                    @click="selectedTableId = table.id"
                >
                    <span class="block font-semibold">{{ table.name }}</span>
                    <span class="mt-1 block text-xs opacity-70">Bo‘sh</span>
                </button>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button class="min-h-11 rounded-lg border border-slate-700 px-4 text-sm disabled:opacity-50" :disabled="busy" type="button" @click="emit('close')">Bekor qilish</button>
                <button class="min-h-11 rounded-lg bg-amber-400 px-4 text-sm font-semibold text-slate-950 disabled:opacity-50" :disabled="busy || selectedTableId === null" type="button" @click="selectedTableId !== null && emit('submit', selectedTableId)">
                    {{ busy ? 'Ko‘chirilmoqda…' : 'Ko‘chirish' }}
                </button>
            </div>
        </section>
    </div>
</template>
