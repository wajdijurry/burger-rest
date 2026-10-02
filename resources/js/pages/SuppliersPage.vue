<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { createSupplier, listSuppliers } from '@/api/resources';
import { errorMessage, fieldErrors, formatFieldLabel, generalErrorMessage } from '@/lib/errors';
import type { Supplier } from '@/types';

const suppliers = ref<Supplier[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);

const name = ref('');
const submitting = ref(false);
const submitError = ref<string | null>(null);
const fieldErrs = ref<Record<string, string[]>>({});

async function load() {
    loading.value = true;
    try {
        suppliers.value = await listSuppliers();
        loadError.value = null;
    } catch (e) {
        loadError.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (submitting.value) return;
    submitting.value = true;
    submitError.value = null;
    fieldErrs.value = {};
    try {
        const created = await createSupplier(name.value.trim());
        suppliers.value = [...suppliers.value, created].sort((a, b) => a.name.localeCompare(b.name));
        name.value = '';
    } catch (e) {
        fieldErrs.value = fieldErrors(e);
        submitError.value = generalErrorMessage(e);
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-xl font-semibold text-slate-800">Suppliers</h1>

        <form class="flex flex-wrap items-end gap-3 rounded border border-slate-200 bg-white p-4" @submit.prevent="submit">
            <div>
                <label for="sup-name" class="block text-xs font-medium text-slate-600">Name</label>
                <input
                    id="sup-name"
                    v-model="name"
                    required
                    maxlength="160"
                    placeholder="Acme Meats Co."
                    class="mt-1 rounded border border-slate-300 px-2 py-1 text-sm"
                />
            </div>
            <button
                type="submit"
                :disabled="submitting"
                class="rounded bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
            >
                {{ submitting ? 'Adding…' : 'Add supplier' }}
            </button>
            <p v-if="submitError" role="alert" class="w-full text-sm text-red-600">{{ submitError }}</p>
            <p v-for="(msgs, field) in fieldErrs" :key="field" class="w-full text-xs text-red-500">
                {{ formatFieldLabel(field) }}: {{ msgs.join(', ') }}
            </p>
        </form>

        <p v-if="loadError" role="alert" class="text-sm text-red-600">{{ loadError }}</p>

        <div class="overflow-hidden rounded border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!loading && suppliers.length === 0">
                        <td class="px-3 py-6 text-center text-slate-400">No suppliers yet.</td>
                    </tr>
                    <tr v-for="sup in suppliers" :key="sup.id" class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ sup.name }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
