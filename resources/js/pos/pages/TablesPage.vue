<script setup lang="ts">
import { useOrderStore } from '../stores/order';
import type { PosTable } from '../types/bootstrap';

defineProps<{ tables: PosTable[] }>();
const emit = defineEmits<{ navigate: [page: string] }>();
const order = useOrderStore();

function select(table: PosTable): void {
    if (table.is_occupied) {
        emit('navigate', 'orders');
        return;
    }
    order.selectTable(table.id);
    emit('navigate', 'pos');
}
</script>

<template>
    <section>
        <h2 class="text-xl font-semibold">Tables</h2>
        <div class="mt-5 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
            <button v-for="table in tables" :key="table.id" class="min-h-28 rounded-xl border p-4 text-left" :class="table.is_occupied ? 'border-red-700 bg-red-500/10' : 'border-emerald-700 bg-emerald-500/10'" type="button" @click="select(table)">
                <span class="block text-lg font-semibold">{{ table.name }}</span>
                <span class="mt-2 block text-sm">{{ table.is_occupied ? 'Occupied' : 'Free' }}</span>
            </button>
        </div>
    </section>
</template>
