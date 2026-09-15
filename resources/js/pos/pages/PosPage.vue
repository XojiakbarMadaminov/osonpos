<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import CustomerPicker from '../components/customers/CustomerPicker.vue';
import type { CustomerSummary } from '../services/api';
import { apiService } from '../services/api';
import { KitchenPrintService } from '../services/kitchen-print';
import { printerService } from '../services/printer';
import { useCartStore } from '../stores/cart';
import { useOrderStore } from '../stores/order';
import { useShiftStore } from '../stores/shift';
import type { PosBootstrap, PosProduct } from '../types/bootstrap';
import { formatMoney } from '../utils/money';

const props = defineProps<{ bootstrap: PosBootstrap }>();
const emit = defineEmits<{ navigate: [page: string] }>();
const cart = useCartStore();
const order = useOrderStore();
const shift = useShiftStore();
const kitchenPrinter = new KitchenPrintService(apiService, printerService);
const selectedCategory = ref<number | null>(props.bootstrap.categories[0]?.id ?? null);
const busy = ref(false);
const error = ref('');
const products = computed(() => props.bootstrap.products.filter(
    (product) => selectedCategory.value === null || product.category_id === selectedCategory.value,
));
const isAddingToOrder = computed(() => order.current?.status === 'OPEN');
const canTakeOrder = computed(() => shift.loaded && shift.current !== null);
const displayedTotal = computed(() => (isAddingToOrder.value ? order.current?.total ?? 0 : 0) + cart.subtotal);

function add(product: PosProduct): void {
    if (!canTakeOrder.value) {
        error.value = 'Buyurtma olish uchun avval smenani oching.';
        return;
    }

    cart.add(product);
}

function cancelAddingProducts(): void {
    cart.clear();
    order.reset();
    error.value = '';
}

function chooseType(type: 'DINE_IN' | 'TAKEAWAY' | 'DELIVERY'): void {
    if (!canTakeOrder.value) {
        error.value = 'Buyurtma olish uchun avval smenani oching.';
        return;
    }

    order.start(type);
    if (type === 'DINE_IN') emit('navigate', 'tables');
    if (type === 'DELIVERY') emit('navigate', 'delivery');
}

async function saveOrder(): Promise<void> {
    if (!canTakeOrder.value) {
        error.value = 'Buyurtma olish uchun avval smenani oching.';
        return;
    }

    if (cart.items.length === 0) return;
    if (order.type === 'DINE_IN' && order.tableId === null) {
        emit('navigate', 'tables');
        return;
    }
    if (order.type === 'DELIVERY' && (!order.customerPhone || !order.deliveryAddress)) {
        emit('navigate', 'delivery');
        return;
    }
    busy.value = true;
    error.value = '';
    try {
        if (isAddingToOrder.value && order.current) {
            const orderId = order.current.id;
            await apiService.addOrderItems(orderId, cart.items);
            order.openExisting(await apiService.order(orderId));
            cart.clear();

            try {
                await kitchenPrinter.send(orderId);
            } catch {
                error.value = 'Buyurtma saqlandi, ammo oshxona cheki chiqmadi. QZ Tray’ni qayta ulang va Buyurtmalar bo‘limidan takrorlang.';

                return;
            }

            emit('navigate', 'orders');
            return;
        }

        const created = await apiService.createOrder({
            type: order.type,
            tableId: order.tableId,
            customerId: order.selectedCustomer?.id,
            customerPhone: order.customerPhone,
            customerName: order.customerName,
            deliveryAddress: order.deliveryAddress,
            deliveryFee: order.deliveryFee,
        });
        await apiService.addOrderItems(created.id, cart.items);
        const savedOrder = await apiService.order(created.id);
        order.openExisting(savedOrder);
        cart.clear();

        try {
            await kitchenPrinter.send(savedOrder.id);
        } catch {
            error.value = 'Buyurtma saqlandi, ammo oshxona cheki chiqmadi. QZ Tray’ni qayta ulang va Buyurtmalar bo‘limidan takrorlang.';

            return;
        }

        emit('navigate', 'payment');
    } catch {
        error.value = 'Buyurtmani saqlab bo‘lmadi. Joriy smena va internet aloqasini tekshiring.';
    } finally {
        busy.value = false;
    }
}

async function setTakeawayCustomer(customer: CustomerSummary | null): Promise<void> {
    error.value = '';
    if (!order.current) {
        order.selectedCustomer = customer;
        return;
    }

    busy.value = true;
    try {
        order.openExisting(customer
            ? await apiService.setOrderCustomer(order.current.id, customer.id)
            : await apiService.removeOrderCustomer(order.current.id));
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Mijozni biriktirib bo‘lmadi.';
    } finally {
        busy.value = false;
    }
}

onMounted(() => shift.load(true));
</script>

<template>
    <section class="grid gap-5 lg:h-full lg:min-h-0 lg:grid-cols-[13rem_1fr_22rem]">
        <div v-if="shift.loaded && !shift.current" class="rounded-xl border border-amber-700 bg-amber-500/10 p-4 text-sm text-amber-200 lg:col-span-3">
            <p>Buyurtma olish uchun avval smenani oching.</p>
            <button class="mt-2 font-semibold text-amber-300 underline" type="button" @click="emit('navigate', 'shift')">Smenaga o‘tish</button>
        </div>
        <p v-else-if="shift.error" class="rounded-xl border border-red-800 bg-red-500/10 p-4 text-sm text-red-200 lg:col-span-3">{{ shift.error }}</p>
        <aside class="rounded-xl border border-slate-800 bg-slate-900 p-3 lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain lg:[scrollbar-gutter:stable]" aria-label="Kategoriyalar">
            <button v-for="category in bootstrap.categories" :key="category.id" class="mb-2 min-h-12 w-full rounded-lg px-4 text-left font-medium" :class="selectedCategory === category.id ? 'bg-amber-400 text-slate-950' : 'bg-slate-800'" type="button" @click="selectedCategory = category.id">
                {{ category.name }}
            </button>
        </aside>
        <div class="grid content-start grid-cols-2 gap-3 rounded-xl border border-slate-800 bg-slate-900 p-4 md:grid-cols-3 lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain lg:[scrollbar-gutter:stable] xl:grid-cols-4">
            <button v-for="product in products" :key="product.id" class="min-h-24 rounded-xl border border-slate-700 bg-slate-800 p-4 text-left hover:border-amber-400 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canTakeOrder" type="button" @click="add(product)">
                <span class="block font-semibold">{{ product.name }}</span>
                <span class="mt-2 block text-sm text-amber-300">{{ formatMoney(product.price) }} UZS</span>
            </button>
        </div>
        <aside class="rounded-xl border border-slate-800 bg-slate-900 p-4 lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain lg:[scrollbar-gutter:stable]">
            <div v-if="isAddingToOrder" class="mb-4 flex items-center justify-between gap-3 rounded-lg border border-amber-400/40 bg-amber-400/10 p-3 text-sm text-amber-200">
                <span><strong>{{ order.current?.display_number }}</strong> buyurtmasiga mahsulot qo‘shilmoqda</span>
                <button class="flex min-h-8 min-w-8 items-center justify-center rounded-md text-xl leading-none hover:bg-amber-400/15" :disabled="busy" type="button" aria-label="Mahsulot qo‘shishni bekor qilish" title="Mahsulot qo‘shishni bekor qilish" @click="cancelAddingProducts">×</button>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <button class="min-h-12 rounded-lg border text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60" :class="order.type === 'DINE_IN' ? 'border-amber-400 bg-amber-400 text-slate-950' : 'border-slate-700 bg-slate-950 text-slate-100 hover:border-slate-500'" :aria-pressed="order.type === 'DINE_IN'" :disabled="isAddingToOrder || !canTakeOrder" type="button" @click="chooseType('DINE_IN')">Zalda</button>
                <button class="min-h-12 rounded-lg border text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60" :class="order.type === 'TAKEAWAY' ? 'border-amber-400 bg-amber-400 text-slate-950' : 'border-slate-700 bg-slate-950 text-slate-100 hover:border-slate-500'" :aria-pressed="order.type === 'TAKEAWAY'" :disabled="isAddingToOrder || !canTakeOrder" type="button" @click="chooseType('TAKEAWAY')">Olib ketish</button>
                <button class="min-h-12 rounded-lg border text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60" :class="order.type === 'DELIVERY' ? 'border-amber-400 bg-amber-400 text-slate-950' : 'border-slate-700 bg-slate-950 text-slate-100 hover:border-slate-500'" :aria-pressed="order.type === 'DELIVERY'" :disabled="isAddingToOrder || !canTakeOrder" type="button" @click="chooseType('DELIVERY')">Yetkazish</button>
            </div>
            <CustomerPicker
                v-if="order.type === 'TAKEAWAY'"
                class="mt-4"
                :disabled="busy"
                :model-value="order.selectedCustomer"
                @update:model-value="setTakeawayCustomer"
            />
            <div class="mt-4 space-y-3">
                <div v-for="(item, index) in cart.items" :key="`${item.productId}-${index}`" class="rounded-xl border border-slate-700/80 bg-slate-800 p-3 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-100">{{ item.name }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ formatMoney(item.unitPrice) }} UZS / dona</p>
                        </div>
                        <button class="flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-red-400/30 bg-red-400/10 text-red-300 transition-colors hover:border-red-400/60 hover:bg-red-400/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400 disabled:opacity-50" :aria-label="`${item.name} mahsulotini savatdan o‘chirish`" :title="`${item.name} mahsulotini savatdan o‘chirish`" :disabled="busy" type="button" @click="cart.remove(index)">
                            <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <div class="inline-flex items-center overflow-hidden rounded-lg border border-slate-600 bg-slate-950" role="group" :aria-label="`${item.name} miqdori`">
                            <button class="flex min-h-11 min-w-11 items-center justify-center text-xl text-slate-200 transition-colors hover:bg-slate-700 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 disabled:cursor-not-allowed disabled:text-slate-600" :aria-label="`${item.name} miqdorini kamaytirish`" :disabled="busy || item.quantity <= 1" type="button" @click="cart.decrement(index)">−</button>
                            <output class="flex min-h-11 min-w-11 items-center justify-center border-x border-slate-600 px-2 font-bold text-amber-300" :aria-label="`${item.name} miqdori: ${item.quantity}`">{{ item.quantity }}</output>
                            <button class="flex min-h-11 min-w-11 items-center justify-center text-xl text-slate-200 transition-colors hover:bg-slate-700 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 disabled:opacity-50" :aria-label="`${item.name} miqdorini oshirish`" :disabled="busy" type="button" @click="cart.increment(index)">+</button>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400">Jami</p>
                            <p class="font-semibold text-slate-100">{{ formatMoney(item.quantity * item.unitPrice) }} UZS</p>
                        </div>
                    </div>
                    <input :value="item.note" class="mt-2 min-h-10 w-full rounded border border-slate-700 bg-slate-950 px-3 text-sm" placeholder="Mahsulot izohi" @input="cart.setNote(index, ($event.target as HTMLInputElement).value)">
                </div>
            </div>
            <div class="mt-5 flex items-center justify-between border-t border-slate-700 pt-4 text-lg font-bold"><span>Jami</span><span>{{ formatMoney(displayedTotal) }} UZS</span></div>
            <p v-if="error" class="mt-3 text-sm text-red-300">{{ error }}</p>
            <button class="mt-4 min-h-14 w-full rounded-xl bg-amber-400 px-5 font-bold text-slate-950 disabled:opacity-50" :disabled="busy || !canTakeOrder || cart.items.length === 0" type="button" @click="saveOrder">
                {{ busy ? 'Saqlanmoqda va chop etilmoqda…' : isAddingToOrder ? 'Qo‘shish va oshxonaga yuborish' : 'Saqlash va oshxonaga yuborish' }}
            </button>
        </aside>
    </section>
</template>
