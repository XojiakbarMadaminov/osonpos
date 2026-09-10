<script setup lang="ts">
import { ref } from 'vue';
import { apiService, type ConfiguredPrinter, type RegisteredDevice } from '../services/api';
import { printerService } from '../services/printer';

const name = ref('');
const code = ref('');
const saving = ref(false);
const error = ref('');
const device = ref<RegisteredDevice | null>(null);
const configuredPrinters = ref<ConfiguredPrinter[]>([]);
const discoveredPrinters = ref<string[]>([]);
const logicalPrinterId = ref<number | null>(null);
const physicalPrinter = ref('');
const qzConnected = ref(false);

async function register(): Promise<void> {
    saving.value = true;
    error.value = '';

    try {
        device.value = await apiService.registerDevice(name.value, code.value);
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Device registration failed.';
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
        error.value = exception instanceof Error ? exception.message : 'Printer discovery failed.';
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
        error.value = exception instanceof Error ? exception.message : 'Test print failed.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-slate-950 p-6 text-slate-100">
        <section class="w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-xl">
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-amber-400">OsonPOS</p>
            <h1 class="mt-3 text-2xl font-semibold">Set up this POS device</h1>
            <p class="mt-2 text-sm text-slate-400">Register this terminal with your current store before taking orders.</p>

            <div v-if="device" class="mt-6 space-y-5">
                <div class="rounded-lg border border-emerald-700 bg-emerald-500/10 p-4 text-emerald-200">
                    {{ device.name }} is ready with code {{ device.code }}.
                </div>
                <button class="min-h-12 w-full rounded-lg border border-amber-400 px-5 font-semibold text-amber-300" :disabled="saving" type="button" @click="discoverPrinters">
                    {{ qzConnected ? 'Refresh printer list' : 'Connect and detect printers' }}
                </button>
                <p class="text-sm" :class="qzConnected ? 'text-emerald-300' : 'text-slate-400'">QZ Tray: {{ qzConnected ? 'connected' : 'not connected' }}</p>
                <form v-if="discoveredPrinters.length" class="space-y-4" @submit.prevent="saveBinding">
                    <select v-model="logicalPrinterId" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" required>
                        <option :value="null" disabled>Select configured printer</option>
                        <option v-for="printer in configuredPrinters" :key="printer.id" :value="printer.id">{{ printer.name }}</option>
                    </select>
                    <select v-model="physicalPrinter" class="min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" required>
                        <option value="" disabled>Select detected printer</option>
                        <option v-for="printerName in discoveredPrinters" :key="printerName" :value="printerName">{{ printerName }}</option>
                    </select>
                    <button class="min-h-12 w-full rounded-lg bg-amber-400 px-5 font-semibold text-slate-950" :disabled="saving" type="submit">Test print and save</button>
                </form>
                <p v-if="error" class="text-sm text-red-300" role="alert">{{ error }}</p>
            </div>

            <form v-else class="mt-6 space-y-5" @submit.prevent="register">
                <label class="block">
                    <span class="text-sm font-medium">Device name</span>
                    <input v-model="name" class="mt-2 min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4" required maxlength="255">
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Device code</span>
                    <input v-model="code" class="mt-2 min-h-12 w-full rounded-lg border border-slate-700 bg-slate-950 px-4 uppercase" required maxlength="255" pattern="[A-Za-z0-9_-]+">
                </label>
                <p v-if="error" class="text-sm text-red-300" role="alert">{{ error }}</p>
                <button class="min-h-12 w-full rounded-lg bg-amber-400 px-5 font-semibold text-slate-950 disabled:opacity-60" :disabled="saving" type="submit">
                    {{ saving ? 'Registering…' : 'Register device' }}
                </button>
            </form>
        </section>
    </main>
</template>
