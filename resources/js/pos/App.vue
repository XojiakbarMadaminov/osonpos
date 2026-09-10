<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ShiftControl from './components/ShiftControl.vue';
import DeliveryOrderPage from './pages/DeliveryOrderPage.vue';
import DeviceSetupPage from './pages/DeviceSetupPage.vue';
import OrdersPage from './pages/OrdersPage.vue';
import PaymentPage from './pages/PaymentPage.vue';
import PosPage from './pages/PosPage.vue';
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
const online = ref(navigator.onLine);
const navigation = computed(() => [
    { id: 'pos', label: 'POS', visible: auth.can('pos.access') },
    { id: 'tables', label: 'Tables', visible: auth.can('tables.view') },
    { id: 'orders', label: 'Orders', visible: auth.can('orders.view') },
    { id: 'shift', label: 'Shift', visible: auth.can('shifts.view') },
].filter((item) => item.visible));

function setOnline(): void {
    online.value = navigator.onLine;
}

onMounted(async () => {
    window.addEventListener('online', setOnline);
    window.addEventListener('offline', setOnline);
    if (isDeviceSetup) return;
    try {
        const payload = await apiService.bootstrap();
        context.hydrate(payload);
        auth.hydrate(payload.user, payload.permissions);
    } catch {
        error.value = 'POS could not start. Confirm your device, store access, and subscription.';
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
    <main v-else class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-6">
        <div class="mx-auto max-w-[100rem]">
            <div v-if="loading" class="flex min-h-[70vh] items-center justify-center text-slate-400">Loading POS…</div>
            <div v-else-if="error" class="mx-auto mt-20 max-w-xl rounded-xl border border-red-800 bg-red-500/10 p-6 text-red-200">
                {{ error }}
                <a class="mt-4 block text-amber-300 underline" href="/pos/device-setup">Open device setup</a>
            </div>
            <template v-else-if="context.bootstrap">
                <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-amber-400">OsonPOS</p>
                        <h1 class="mt-2 text-xl font-semibold">{{ context.bootstrap.organization.name }} · {{ context.bootstrap.store.name }}</h1>
                        <p class="mt-1 text-sm text-slate-400">{{ context.bootstrap.user.name }} · {{ context.bootstrap.device.name }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-sm" :class="online ? 'bg-emerald-500/15 text-emerald-300' : 'bg-red-500/15 text-red-300'">{{ online ? 'Online' : 'Offline' }}</span>
                        <ShiftControl v-if="auth.can('shifts.view')" />
                    </div>
                </header>

                <nav class="my-5 flex flex-wrap gap-3" aria-label="POS navigation">
                    <button v-for="item in navigation" :key="item.id" class="min-h-12 rounded-lg border px-5 font-medium" :class="page === item.id ? 'border-amber-400 text-amber-300' : 'border-slate-700'" type="button" @click="page = item.id">
                        {{ item.label }}
                    </button>
                </nav>

                <PosPage v-if="page === 'pos'" :bootstrap="context.bootstrap" @navigate="page = $event" />
                <TablesPage v-else-if="page === 'tables'" :tables="context.bootstrap.tables" @navigate="page = $event" />
                <OrdersPage v-else-if="page === 'orders'" />
                <DeliveryOrderPage v-else-if="page === 'delivery'" @navigate="page = $event" />
                <PaymentPage v-else-if="page === 'payment'" @navigate="page = $event" />
                <section v-else-if="page === 'shift'" class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-xl font-semibold">Shift</h2>
                    <p class="mt-2 text-slate-400">Use the shift controls in the header to open or close your current register.</p>
                </section>
            </template>
        </div>
    </main>
</template>
