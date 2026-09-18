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
const flash = (window as Window & { __OSONPOS_FLASH__?: { logoutError?: string | null } }).__OSONPOS_FLASH__;
const navigationError = ref(flash?.logoutError ?? '');
const online = ref(navigator.onLine);
const refreshingContext = ref(false);
const navigation = computed(() => [
    { id: 'pos', label: 'POS', visible: auth.can('pos.access') },
    { id: 'tables', label: 'Stollar', visible: auth.can('tables.view') },
    { id: 'orders', label: 'Buyurtmalar', visible: auth.can('orders.view') },
    { id: 'shift', label: 'Smena', visible: auth.can('shifts.view') },
].filter((item) => item.visible));
const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

function setOnline(): void {
    online.value = navigator.onLine;
}

function confirmLogout(event: SubmitEvent): void {
    if (!window.confirm('Akkauntdan chiqmoqchimisiz? Keyingi kassir o‘z akkaunti bilan kirishi kerak.')) {
        event.preventDefault();
    }
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
                <header class="mb-4 flex shrink-0 flex-wrap items-center gap-4 border-b border-slate-800 pb-4 md:gap-6">
                    <div class="flex items-center gap-4 md:border-r md:border-slate-800 md:pr-6">
                        <h1 class="text-xl font-bold uppercase tracking-widest text-amber-400">OSONPOS</h1>
                    </div>

                    <nav class="flex shrink-0 gap-2 overflow-x-auto" aria-label="POS menyusi">
                        <button v-for="item in navigation" :key="item.id" class="flex h-10 items-center whitespace-nowrap rounded-lg border px-4 text-sm font-medium transition-colors disabled:opacity-60" :class="page === item.id ? 'border-amber-400 bg-amber-400/10 text-amber-300' : 'border-slate-700 text-slate-300 hover:bg-slate-800 hover:text-slate-100'" :disabled="refreshingContext" type="button" @click="navigate(item.id)">
                            {{ item.label }}
                        </button>
                    </nav>

                    <div class="ml-auto flex items-center gap-4">
                        <div class="hidden text-right leading-snug lg:block">
                            <p class="text-sm"><span class="font-semibold text-slate-200">{{ context.bootstrap.organization.name }}</span> <span class="text-slate-400">· {{ context.bootstrap.store.name }}</span></p>
                            <p class="text-xs text-slate-400">{{ context.bootstrap.user.name }} <span class="opacity-70">({{ context.bootstrap.device.name }})</span></p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="online ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400'">{{ online ? 'Onlayn' : 'Oflayn' }}</span>
                        <form action="/pos/logout" method="POST" @submit="confirmLogout">
                            <input name="_token" type="hidden" :value="csrfToken">
                            <button class="min-h-10 rounded-lg border border-slate-700 px-3 text-sm font-medium text-slate-300 transition-colors hover:border-red-400 hover:bg-red-500/10 hover:text-red-300" type="submit">
                                Chiqish
                            </button>
                        </form>
                    </div>
                </header>
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
