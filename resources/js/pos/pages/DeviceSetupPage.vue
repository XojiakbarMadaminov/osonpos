<script setup lang="ts">
import { ref } from 'vue';
import { apiService, type ConfiguredPrinter, type RegisteredDevice } from '../services/api';
import { printerService } from '../services/printer';

interface PosSetupState {
    device: RegisteredDevice | null;
    can_manage_printers: boolean;
}

const setup = (window as Window & { __OSONPOS_SETUP__?: PosSetupState }).__OSONPOS_SETUP__;
const activationCode = ref('');
const saving = ref(false);
const error = ref('');
const device = ref<RegisteredDevice | null>(setup?.device ?? null);
const canManagePrinters = setup?.can_manage_printers ?? false;
const configuredPrinters = ref<ConfiguredPrinter[]>([]);
const discoveredPrinters = ref<string[]>([]);
const logicalPrinterId = ref<number | null>(null);
const physicalPrinter = ref('');
const qzConnected = ref(false);

async function activate(): Promise<void> {
    saving.value = true;
    error.value = '';

    try {
        await apiService.activateDevice(activationCode.value);
        window.location.assign('/pos');
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Qurilmani ro‘yxatdan o‘tkazib bo‘lmadi.';
    } finally {
        saving.value = false;
    }
}

async function discoverPrinters(): Promise<void> {
    saving.value = true;
    error.value = '';

    try {
        [configuredPrinters.value, discoveredPrinters.value] = await Promise.all([
            apiService.printers(),
            printerService.discover(),
        ]);
        qzConnected.value = printerService.isConnected();
    } catch (exception) {
        qzConnected.value = false;
        error.value = exception instanceof Error ? exception.message : 'Printerlarni aniqlab bo‘lmadi.';
    } finally {
        saving.value = false;
    }
}

async function saveBinding(): Promise<void> {
    if (logicalPrinterId.value === null || physicalPrinter.value === '') return;
    saving.value = true;
    error.value = '';

    try {
        await printerService.printTest(physicalPrinter.value);
        await apiService.bindPrinter(logicalPrinterId.value, physicalPrinter.value);
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Sinov chekini chiqarib bo‘lmadi.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-slate-950 p-6 text-slate-100">
        <section class="w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-xl">
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-amber-400">OsonPOS</p>
            <h1 class="mt-3 text-2xl font-semibold">POS qurilmasini sozlash</h1>
            <p class="mt-2 text-sm text-slate-400">Buyurtma olishdan oldin ushbu terminalni joriy filialga ro‘yxatdan o‘tkazing.</p>

            <div v-if="device" class="mt-6 space-y-5">
                <div class="rounded-lg border border-emerald-700 bg-emerald-500/10 p-4 text-emerald-200">
                    {{ device.name }} qurilmasi tayyor.
                </div>
                <a class="flex min-h-12 w-full items-center justify-center rounded-lg bg-amber-400 px-5 font-semibold text-slate-950" href="/pos">
                    POS’ga o‘tish
                </a>
                <p v-if="canManagePrinters" class="text-center text-sm text-slate-400">Printerni hozir yoki keyinroq ulashingiz mumkin.</p>
                <button v-if="canManagePrinters" class="min-h-12 w-full rounded-lg border border-amber-400 px-5 font-semibold text-amber-300" :disabled="saving" type="button" @click="discoverPrinters">
                    {{ qzConnected ? 'Printerlar ro‘yxatini yangilash' : 'Ulanish va printerlarni aniqlash' }}
                </button>
                <p v-if="canManagePrinters" class="text-sm" :class="qzConnected ? 'text-emerald-300' : 'text-slate-400'">QZ Tray: {{ qzConnected ? 'ulangan' : 'ulanmagan' }}</p>
                <form v-if="canManagePrinters && discoveredPrinters.length" class="space-y-4" @submit.prevent="saveBinding">
                    <select v-model="logicalPrinterId" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" required>
                        <option :value="null" disabled>Sozlangan printerni tanlang</option>
                        <option v-for="printer in configuredPrinters" :key="printer.id" :value="printer.id">{{ printer.name }}</option>
                    </select>
                    <select v-model="physicalPrinter" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" required>
                        <option value="" disabled>Aniqlangan printerni tanlang</option>
                        <option v-for="printerName in discoveredPrinters" :key="printerName" :value="printerName">{{ printerName }}</option>
                    </select>
                    <button class="min-h-12 w-full rounded-lg bg-amber-400 px-5 font-semibold text-slate-950" :disabled="saving" type="submit">Sinov cheki va saqlash</button>
                </form>
                <p v-if="error" class="text-sm text-red-300" role="alert">{{ error }}</p>
            </div>

            <form v-else class="mt-6 space-y-5" @submit.prevent="activate">
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-4 text-sm text-slate-300">
                    Tashkilot egasi yoki boshqaruvchi qurilma uchun belgilagan doimiy aktivatsiya kodini kiriting.
                </div>
                <label class="block">
                    <span class="text-sm font-medium">Aktivatsiya kodi</span>
                    <input v-model="activationCode" class="mt-2 min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 text-center text-xl font-semibold tracking-[0.3em]" required minlength="6" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="current-password">
                </label>
                <p v-if="error" class="text-sm text-red-300" role="alert">{{ error }}</p>
                <button class="min-h-12 w-full rounded-lg bg-amber-400 px-5 font-semibold text-slate-950 disabled:opacity-60" :disabled="saving" type="submit">
                    {{ saving ? 'Aktivatsiya qilinmoqda…' : 'Qurilmani aktivatsiya qilish' }}
                </button>
            </form>
        </section>
    </main>
</template>
