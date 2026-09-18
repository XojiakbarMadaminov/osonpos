<script setup lang="ts">
import type { CreatedOrder } from '../../services/api';
import { formatMoney } from '../../utils/money';

defineProps<{
    order: CreatedOrder;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-labelledby="view-items-title" @click.self="emit('close')">
        <section class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 p-5">
                <h3 id="view-items-title" class="text-xl font-semibold">Buyurtma #{{ order.display_number }}</h3>
                <button class="min-h-10 rounded-lg border border-slate-700 px-4 text-sm" type="button" @click="emit('close')">Yopish</button>
            </div>
            
            <div class="flex-1 overflow-y-auto p-5">
                <ul v-if="order.items && order.items.length > 0" class="divide-y divide-slate-800">
                    <li v-for="item in order.items" :key="item.id" class="py-4 flex justify-between items-start first:pt-0 last:pb-0">
                        <div>
                            <p class="font-medium text-slate-200">{{ item.product_name }}</p>
                            <p class="text-sm text-slate-400 mt-1">
                                {{ item.quantity }} x {{ formatMoney(item.unit_price) }} UZS
                            </p>
                            <p v-if="item.note" class="text-xs text-amber-300 mt-1">
                                {{ item.note }}
                            </p>
                        </div>
                        <div class="text-right shrink-0 ml-4">
                            <p class="font-semibold text-slate-200">{{ formatMoney(item.total) }} UZS</p>
                            <p v-if="item.removed_quantity > 0" class="text-xs text-red-400 mt-1">
                                -{{ item.removed_quantity }} bekor qilingan
                            </p>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-center text-slate-400 py-8">Mahsulotlar topilmadi.</p>
            </div>
            
            <div class="space-y-2 border-t border-slate-800 bg-slate-800/50 p-5">
                <div class="flex items-center justify-between text-sm text-slate-400">
                    <span>Oraliq jami</span>
                    <span>{{ formatMoney(order.subtotal) }} UZS</span>
                </div>
                <div v-if="order.discount_amount > 0" class="flex items-center justify-between text-sm text-emerald-400">
                    <span>Chegirma <template v-if="order.discount_type === 'PERCENTAGE'">({{ order.discount_value }}%)</template></span>
                    <span>−{{ formatMoney(order.discount_amount) }} UZS</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Jami hisob:</span>
                    <span class="text-xl font-bold text-emerald-400">{{ formatMoney(order.total) }} UZS</span>
                </div>
            </div>
        </section>
    </div>
</template>
