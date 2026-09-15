<script setup lang="ts">
import { computed, reactive } from 'vue';
import type { CreatedOrder, OrderItemSummary } from '../../services/api';
import { formatMoney } from '../../utils/money';

const props = defineProps<{ order: CreatedOrder; busy: boolean }>();
const emit = defineEmits<{
    close: [];
    submit: [items: Array<{ order_item_id: string; quantity: number }>];
}>();
const quantities = reactive<Record<string, number>>({});
const selectedCount = computed(() => (props.order.items ?? []).reduce(
    (sum, item) => sum + item.quantity - remaining(item),
    0,
));

function remaining(item: OrderItemSummary): number {
    return quantities[item.id] ?? item.quantity;
}

function decrement(item: OrderItemSummary): void {
    if (remaining(item) > 0) quantities[item.id] = remaining(item) - 1;
}

function increment(item: OrderItemSummary): void {
    if (remaining(item) < item.quantity) quantities[item.id] = remaining(item) + 1;
}

function submit(): void {
    const items = (props.order.items ?? [])
        .filter((item) => remaining(item) < item.quantity)
        .map((item) => ({ order_item_id: item.id, quantity: item.quantity - remaining(item) }));
    if (items.length > 0) emit('submit', items);
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-labelledby="remove-items-title" @click.self="emit('close')">
        <section class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 id="remove-items-title" class="text-xl font-semibold">Mahsulot ayirish</h3>
                    <p class="mt-1 text-sm text-slate-400">{{ order.display_number }} · ayiriladigan miqdorni tanlang</p>
                </div>
                <button class="min-h-10 rounded-lg border border-slate-700 px-4 text-sm" :disabled="busy" type="button" @click="emit('close')">Yopish</button>
            </div>

            <div v-if="(order.items ?? []).length" class="mt-5 space-y-3">
                <article v-for="item in order.items" :key="item.id" class="flex items-center justify-between gap-4 rounded-xl border border-slate-700 p-4">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ item.product_name }}</p>
                        <p class="mt-1 text-sm text-slate-400">Qoldi: {{ item.quantity }} · {{ formatMoney(item.unit_price) }} UZS</p>
                        <p v-if="item.kitchen_printed" class="mt-1 text-xs text-amber-300">Oshxonaga chiqarilgan</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button class="h-11 w-11 rounded-lg border border-slate-600 text-xl disabled:opacity-40" :disabled="busy || remaining(item) >= item.quantity" type="button" :aria-label="`${item.product_name} miqdorini qaytarish`" @click="increment(item)">+</button>
                        <span class="w-8 text-center font-semibold text-slate-100">{{ remaining(item) }}</span>
                        <button class="h-11 w-11 rounded-lg bg-red-500 text-xl font-bold text-white disabled:opacity-40" :disabled="busy || remaining(item) === 0" type="button" :aria-label="`${item.product_name}dan bittasini ayirish`" @click="decrement(item)">−</button>
                    </div>
                </article>
            </div>
            <p v-else class="mt-5 rounded-xl border border-dashed border-slate-700 p-5 text-center text-slate-400">Buyurtmada ayiriladigan mahsulot yo‘q.</p>

            <button class="mt-5 min-h-12 w-full rounded-lg bg-red-500 font-semibold text-white disabled:opacity-40" :disabled="busy || selectedCount === 0" type="button" @click="submit">
                {{ busy ? 'Saqlanmoqda…' : `Ayirishni tasdiqlash (${selectedCount})` }}
            </button>
        </section>
    </div>
</template>
