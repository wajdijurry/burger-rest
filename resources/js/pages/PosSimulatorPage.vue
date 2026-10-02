<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { listMenuItems, recordSale } from '@/api/resources';
import { useIdempotencyKey } from '@/composables/useIdempotencyKey';
import { errorMessage, fieldErrors, generalErrorMessage } from '@/lib/errors';
import type { MenuItem } from '@/types';

const menuItems = ref<MenuItem[]>([]);
const loadError = ref<string | null>(null);

const menuItemId = ref(0);
const quantity = ref(1);

const { key: eventId, rotate: rotateKey } = useIdempotencyKey();
const submitting = ref(false);
const submitError = ref<string | null>(null);
const fieldErrs = ref<Record<string, string[]>>({});
const lastResult = ref<{ replayed: boolean; itemName: string; quantity: number } | null>(null);

// A different item/quantity is a genuinely new sale intent, not a retry of
// the previous click, so give it a fresh event_id. A failed-then-retried
// click with the SAME item/quantity keeps reusing the current key.
watch([menuItemId, quantity], () => {
    if (!submitting.value) rotateKey();
});

async function load() {
    try {
        menuItems.value = await listMenuItems();
        loadError.value = null;
    } catch (e) {
        loadError.value = errorMessage(e);
    }
}

async function submit() {
    if (submitting.value || !menuItemId.value) return;
    submitting.value = true;
    submitError.value = null;
    fieldErrs.value = {};
    try {
        const result = await recordSale(eventId.value, Number(menuItemId.value), Number(quantity.value));
        const item = menuItems.value.find((m) => m.id === Number(menuItemId.value));
        lastResult.value = {
            replayed: result.replayed,
            itemName: item?.name ?? `#${menuItemId.value}`,
            quantity: Number(quantity.value),
        };
        rotateKey(); // success: next click (even with the same inputs) is a new sale
    } catch (e) {
        fieldErrs.value = fieldErrors(e);
        submitError.value = generalErrorMessage(e);
        // Keep the current key: a retry of this exact failed click must
        // stay idempotent with itself.
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-md space-y-6">
        <h1 class="text-xl font-semibold text-slate-800">POS Simulator</h1>
        <p class="text-sm text-slate-500">
            Simulates a point-of-sale "item sold" event. Recording a sale consumes each recipe ingredient according
            to the menu item's recipe, deducting stock immediately.
        </p>

        <p v-if="loadError" role="alert" class="text-sm text-red-600">{{ loadError }}</p>

        <form class="space-y-3 rounded border border-slate-200 bg-white p-4" @submit.prevent="submit">
            <div>
                <label for="pos-item" class="block text-xs font-medium text-slate-600">Menu item</label>
                <select
                    id="pos-item"
                    v-model.number="menuItemId"
                    required
                    class="mt-1 w-full rounded border border-slate-300 px-2 py-1 text-sm"
                >
                    <option :value="0" disabled>Select menu item…</option>
                    <option v-for="item in menuItems" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </div>
            <div>
                <label for="pos-qty" class="block text-xs font-medium text-slate-600">Quantity sold</label>
                <input
                    id="pos-qty"
                    v-model.number="quantity"
                    type="number"
                    min="1"
                    max="10000"
                    required
                    class="mt-1 w-24 rounded border border-slate-300 px-2 py-1 text-sm"
                />
            </div>
            <button
                type="submit"
                :disabled="submitting || !menuItemId"
                class="rounded bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
            >
                {{ submitting ? 'Recording…' : 'Record sale' }}
            </button>
            <p v-if="menuItems.length === 0" class="text-xs text-slate-400">No menu items yet — create one first.</p>
            <p v-if="submitError" role="alert" class="text-sm text-red-600">{{ submitError }}</p>
            <p v-for="(msgs, field) in fieldErrs" :key="field" class="text-xs text-red-500">
                {{ field }}: {{ msgs.join(', ') }}
            </p>
            <p v-if="lastResult" class="text-sm text-emerald-700">
                Sold {{ lastResult.quantity }} × {{ lastResult.itemName }}.
                <span v-if="lastResult.replayed" class="text-slate-400">
                    (recognised as a repeat of a request already recorded — stock was not deducted twice)
                </span>
            </p>
        </form>
    </div>
</template>
