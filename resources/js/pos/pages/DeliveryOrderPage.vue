<script setup lang="ts">
import { useOrderStore } from '../stores/order';

const emit = defineEmits<{ navigate: [page: string] }>();
const order = useOrderStore();
order.start('DELIVERY');
</script>

<template>
    <form class="mx-auto max-w-2xl space-y-5 rounded-xl border border-slate-800 bg-slate-900 p-6" @submit.prevent="emit('navigate', 'pos')">
        <h2 class="text-xl font-semibold">Delivery details</h2>
        <input v-model="order.customerPhone" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" placeholder="Customer phone" required>
        <input v-model="order.customerName" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" placeholder="Customer name (optional)">
        <textarea v-model="order.deliveryAddress" class="min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 p-4" placeholder="Delivery address" required />
        <input v-model.number="order.deliveryFee" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" min="0" placeholder="Delivery fee" type="number">
        <button class="min-h-14 w-full rounded-xl bg-amber-400 font-bold text-slate-950" type="submit">Choose products</button>
    </form>
</template>
