<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DeliveryOrderPage from './pages/DeliveryOrderPage.vue';
import DeviceSetupPage from './pages/DeviceSetupPage.vue';
import OrdersPage from './pages/OrdersPage.vue';
import PaymentPage from './pages/PaymentPage.vue';
import PosPage from './pages/PosPage.vue';
import ShiftPage from './pages/ShiftPage.vue';
import TablesPage from './pages/TablesPage.vue';
import { apiService } from './services/api';
import { useAuthStore } from './stores/auth';
import { useContextStore } from './stores/context';

const isDeviceSetup = window.location.pathname === '/pos/device-setup';
const auth = useAuthStore();
const context = useContextStore();
const page = ref('pos');
const loading = ref(!isDeviceSetup);
const error = ref('');
const navigationError = ref('');
const online = ref(navigator.onLine);
const refreshingContext = ref(false);
const navigation = computed(() => [
    { id: 'pos', label: 'POS', visible: auth.can('pos.access') },
    { id: 'tables', label: 'Stollar', visible: auth.can('tables.view') },
    { id: 'orders', label: 'Buyurtmalar', visible: auth.can('orders.view') },
    { id: 'shift', label: 'Smena', visible: auth.can('shifts.view') },
].filter((item) => item.visible));

function setOnline(): void {
    online.value = navigator.onLine;
}

async function navigate(destination: string): Promise<void> {
    navigationError.value = '';
    if (destination === 'tables') {
        refreshingContext.value = true;
        try {
            const payload = await apiService.bootstrap();
            context.hydrate(payload);
            auth.hydrate(payload.user, payload.permissions);
        } catch (exception) {
            navigationError.value = exception instanceof Error ? exception.message : 'Stollar holatini yangilab bo‘lmadi.';
        } finally {
            refreshingContext.value = false;
        }
    }

    page.value = destination;
}

onMounted(async () => {
    window.addEventListener('online', setOnline);
    window.addEventListener('offline', setOnline);
    if (isDeviceSetup) return;
    try {
        const payload = await apiService.bootstrap();
        context.hydrate(payload);
        auth.hydrate(payload.user, payload.permissions);
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'POS tizimini ishga tushirib bo‘lmadi.';
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('online', setOnline);
    window.removeEventListener('offline', setOnline);
});
</script>

<template>
    <DeviceSetupPage v-if="isDeviceSetup" />
    <main v-else class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-6" :class="{ 'lg:h-dvh lg:overflow-hidden': page === 'pos' }">
        <div class="mx-auto max-w-[100rem]" :class="{ 'lg:flex lg:h-full lg:flex-col': page === 'pos' }">
            <div v-if="loading" class="flex min-h-[70vh] items-center justify-center text-slate-400">POS yuklanmoqda…</div>
            <div v-else-if="error" class="mx-auto mt-20 max-w-xl rounded-xl border border-red-800 bg-red-500/10 p-6 text-red-200">
                {{ error }}
                <a class="mt-4 block text-amber-300 underline" href="/pos/device-setup">Qurilmani sozlash</a>
            </div>
            <template v-else-if="context.bootstrap">
                <header class="flex shrink-0 flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-amber-400">OsonPOS</p>
                        <h1 class="mt-2 text-xl font-semibold">{{ context.bootstrap.organization.name }} · {{ context.bootstrap.store.name }}</h1>
                        <p class="mt-1 text-sm text-slate-400">{{ context.bootstrap.user.name }} · {{ context.bootstrap.device.name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-sm" :class="online ? 'bg-emerald-500/15 text-emerald-300' : 'bg-red-500/15 text-red-300'">{{ online ? 'Onlayn' : 'Oflayn' }}</span>
                    </div>
                </header>

                <nav class="my-5 flex shrink-0 flex-wrap gap-3" aria-label="POS menyusi">
                    <button v-for="item in navigation" :key="item.id" class="min-h-12 rounded-lg border px-5 font-medium disabled:opacity-60" :class="page === item.id ? 'border-amber-400 text-amber-300' : 'border-slate-700'" :disabled="refreshingContext" type="button" @click="navigate(item.id)">
                        {{ item.label }}
                    </button>
                </nav>
                <p v-if="navigationError" class="mb-5 rounded-lg border border-red-800 bg-red-500/10 p-3 text-sm text-red-200">{{ navigationError }}</p>

                <PosPage v-if="page === 'pos'" class="lg:min-h-0 lg:flex-1" :bootstrap="context.bootstrap" @navigate="navigate" />
                <TablesPage v-else-if="page === 'tables'" :tables="context.bootstrap.tables" @navigate="navigate" />
                <OrdersPage v-else-if="page === 'orders'" @navigate="navigate" />
                <DeliveryOrderPage v-else-if="page === 'delivery'" @navigate="navigate" />
                <PaymentPage v-else-if="page === 'payment'" @navigate="navigate" />
                <ShiftPage v-else-if="page === 'shift'" />
            </template>
        </div>
    </main>
</template>
