<script setup lang="ts">
import { ref, watch } from 'vue';
import { apiService, type CustomerSummary } from '../../services/api';
import { formatUzbekPhone, nationalPhoneDigits, uzbekPhone } from '../../utils/customer';

const props = withDefaults(defineProps<{
    modelValue: CustomerSummary | null;
    addLabel?: string;
    disabled?: boolean;
}>(), {
    addLabel: '+ Mijoz qo‘shish',
    disabled: false,
});
const emit = defineEmits<{ 'update:modelValue': [customer: CustomerSummary | null] }>();

const expanded = ref(false);
const digits = ref('');
const name = ref('');
const result = ref<CustomerSummary | null>(null);
const searched = ref(false);
const busy = ref(false);
const error = ref('');

watch(() => props.modelValue, (customer) => {
    if (customer) {
        digits.value = nationalPhoneDigits(customer.phone);
        name.value = customer.name ?? '';
    }
}, { immediate: true });

function updatePhone(event: Event): void {
    const input = event.target as HTMLInputElement;
    const nextDigits = input.value.replace(/\D/g, '').slice(0, 9);
    if (nextDigits !== digits.value) name.value = '';
    digits.value = nextDigits;
    input.value = digits.value;
    searched.value = false;
    result.value = null;
    error.value = '';
}

async function search(): Promise<void> {
    const phone = uzbekPhone(digits.value);
    if (!phone) {
        error.value = '9 xonali telefon raqamini kiriting.';
        return;
    }

    busy.value = true;
    error.value = '';
    try {
        result.value = await apiService.findCustomerByPhone(phone);
        searched.value = true;
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Mijozni topib bo‘lmadi.';
    } finally {
        busy.value = false;
    }
}

function select(customer: CustomerSummary): void {
    emit('update:modelValue', customer);
    expanded.value = false;
    searched.value = false;
}

async function createCustomer(): Promise<void> {
    const phone = uzbekPhone(digits.value);
    if (!phone) {
        error.value = '9 xonali telefon raqamini kiriting.';
        return;
    }

    busy.value = true;
    error.value = '';
    try {
        select(await apiService.createCustomer(phone, name.value));
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'Mijozni yaratib bo‘lmadi.';
    } finally {
        busy.value = false;
    }
}

function change(): void {
    result.value = null;
    searched.value = false;
    expanded.value = true;
}

function remove(): void {
    emit('update:modelValue', null);
    digits.value = '';
    name.value = '';
    result.value = null;
    searched.value = false;
    expanded.value = false;
}
</script>

<template>
    <div class="rounded-xl border border-slate-700/80 bg-slate-950/40 p-3">
        <div v-if="modelValue && !expanded" class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Mijoz</p>
            <p class="font-semibold">{{ modelValue.name || 'Ismsiz mijoz' }}</p>
            <p class="text-sm text-slate-300">{{ formatUzbekPhone(modelValue.phone) }}</p>
            <div class="flex gap-2">
                <button class="min-h-11 flex-1 rounded-lg border border-slate-600 px-3 text-sm" :disabled="disabled" type="button" @click="change">O‘zgartirish</button>
                <button class="min-h-11 flex-1 rounded-lg border border-red-500/50 px-3 text-sm text-red-300" :disabled="disabled" type="button" @click="remove">Olib tashlash</button>
            </div>
        </div>

        <button v-else-if="!expanded" class="min-h-11 w-full rounded-lg border border-amber-400/60 px-3 text-sm font-semibold text-amber-300" :disabled="disabled" type="button" @click="expanded = true">
            {{ addLabel }}
        </button>

        <div v-else class="space-y-3">
            <div class="flex min-h-12 items-center rounded-lg border border-slate-700 bg-slate-950 focus-within:border-amber-400">
                <span class="border-r border-slate-700 px-3 text-sm text-slate-300">+998</span>
                <input :value="digits" aria-label="Mijoz telefon raqami" autocomplete="tel" class="min-h-12 min-w-0 flex-1 bg-transparent px-3 outline-none" inputmode="numeric" maxlength="9" pattern="[0-9]{9}" placeholder="901234567" type="tel" @input="updatePhone">
            </div>
            <button class="min-h-11 w-full rounded-lg bg-slate-700 px-3 font-medium disabled:opacity-50" :disabled="busy || digits.length !== 9" type="button" @click="search">
                {{ busy ? 'Qidirilmoqda…' : 'Telefon orqali qidirish' }}
            </button>

            <div v-if="searched && result" class="rounded-lg border border-emerald-600/50 bg-emerald-500/10 p-3">
                <p class="font-semibold">{{ result.name || 'Ismsiz mijoz' }}</p>
                <p class="mt-1 text-sm text-slate-300">{{ formatUzbekPhone(result.phone) }}</p>
                <button class="mt-3 min-h-11 w-full rounded-lg bg-emerald-500 px-3 font-semibold text-slate-950" type="button" @click="select(result)">Mijozni tanlash</button>
            </div>

            <div v-else-if="searched" class="space-y-3 rounded-lg border border-slate-700 p-3">
                <p class="text-sm text-slate-300">Bu telefon bilan mijoz topilmadi. Yangi mijoz yaratishingiz mumkin.</p>
                <input v-model="name" class="min-h-11 w-full rounded-lg border border-slate-700 bg-slate-950 px-3" maxlength="255" placeholder="Mijoz ismi (ixtiyoriy)">
                <button class="min-h-11 w-full rounded-lg bg-amber-400 px-3 font-semibold text-slate-950 disabled:opacity-50" :disabled="busy" type="button" @click="createCustomer">Yangi mijoz yaratish</button>
            </div>

            <p v-if="error" class="text-sm text-red-300" role="alert">{{ error }}</p>
            <button class="min-h-11 w-full rounded-lg border border-slate-700 px-3 text-sm" :disabled="busy" type="button" @click="expanded = false">Yopish</button>
        </div>
    </div>
</template>
