<script setup lang="ts">
import { computed } from 'vue';
import { useOrderStore } from '../stores/order';

const emit = defineEmits<{ navigate: [page: string] }>();
const order = useOrderStore();
order.start('DELIVERY');

const nationalPhone = computed(() => order.customerPhone.replace(/^\+998/, '').replace(/\D/g, '').slice(0, 9));

function updatePhone(event: Event): void {
    const input = event.target as HTMLInputElement;
    const digits = input.value.replace(/\D/g, '').slice(0, 9);
    input.value = digits;
    order.customerPhone = digits.length > 0 ? `+998${digits}` : '';
}
</script>

<template>
    <form class="mx-auto max-w-2xl space-y-5 rounded-xl border border-slate-800 bg-slate-900 p-6" @submit.prevent="emit('navigate', 'pos')">
        <h2 class="text-xl font-semibold">Yetkazib berish ma’lumotlari</h2>
        <label class="flex min-h-12 w-full items-center rounded-lg border border-slate-700 bg-slate-950 focus-within:border-amber-400">
            <span class="border-r border-slate-700 px-4 font-medium text-slate-300">+998</span>
            <input :value="nationalPhone" aria-label="Mijoz telefon raqami" autocomplete="tel" class="min-h-12 min-w-0 flex-1 bg-transparent px-4 outline-none" inputmode="numeric" maxlength="9" pattern="[0-9]{9}" placeholder="901234567" required type="tel" @input="updatePhone">
        </label>
        <input v-model="order.customerName" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" placeholder="Mijoz ismi (ixtiyoriy)">
        <textarea v-model="order.deliveryAddress" class="min-h-28 w-full rounded-lg border border-slate-700 bg-slate-950 p-4" placeholder="Yetkazib berish manzili" required />
        <input v-model.number="order.deliveryFee" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" min="0" placeholder="Yetkazib berish narxi" type="number">
        <button class="min-h-14 w-full rounded-xl bg-amber-400 font-bold text-slate-950" type="submit">Mahsulotlarni tanlash</button>
    </form>
</template>
