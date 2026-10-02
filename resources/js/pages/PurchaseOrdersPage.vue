<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import {
    createPurchaseOrder,
    listIngredients,
    listPurchaseOrders,
    listSuppliers,
    sendPurchaseOrder,
    type LineInput,
} from '@/api/resources';
import { errorMessage, fieldErrors } from '@/lib/errors';
import type { Ingredient, PurchaseOrder, Supplier } from '@/types';

const orders = ref<PurchaseOrder[]>([]);
const suppliers = ref<Supplier[]>([]);
const ingredients = ref<Ingredient[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);
const filter = ref<'all' | 'open'>('open');

const supplierId = ref(0);
const lines = reactive<LineInput[]>([{ ingredient_id: 0, quantity: '' }]);
const submitting = ref(false);
const submitError = ref<string | null>(null);
const fieldErrs = ref<Record<string, string[]>>({});

const sendingId = ref<number | null>(null);
const sendError = ref<string | null>(null);

function addLine() {
    lines.push({ ingredient_id: 0, quantity: '' });
}
function removeLine(index: number) {
    if (lines.length > 1) lines.splice(index, 1);
}

async function load() {
    loading.value = true;
    try {
        const [os, sups, ings] = await Promise.all([
            listPurchaseOrders(filter.value === 'open' ? 'open' : undefined),
            listSuppliers(),
            listIngredients(),
        ]);
        orders.value = os;
        suppliers.value = sups;
        ingredients.value = ings;
        loadError.value = null;
    } catch (e) {
        loadError.value = errorMessage(e);
    } finally {
        loading.value = false;
    }
}

function resetForm() {
    supplierId.value = 0;
    lines.splice(0, lines.length, { ingredient_id: 0, quantity: '' });
}

async function submit() {
    if (submitting.value) return;
    submitting.value = true;
    submitError.value = null;
    fieldErrs.value = {};
    try {
        const payload = lines.map((l) => ({ ingredient_id: Number(l.ingredient_id), quantity: l.quantity.trim() }));
        await createPurchaseOrder(Number(supplierId.value), payload);
        resetForm();
        await load();
    } catch (e) {
        submitError.value = errorMessage(e);
        fieldErrs.value = fieldErrors(e);
    } finally {
        submitting.value = false;
    }
}

async function send(order: PurchaseOrder) {
    if (sendingId.value !== null) return; // one send-in-flight at a time
    sendingId.value = order.id;
    sendError.value = null;
    try {
        const updated = await sendPurchaseOrder(order.id);
        const idx = orders.value.findIndex((o) => o.id === updated.id);
        if (idx !== -1) {
            if (filter.value === 'open') {
                orders.value.splice(idx, 1, updated); // still open (now "sent")
            } else {
                orders.value.splice(idx, 1, updated);
            }
        }
    } catch (e) {
        sendError.value = errorMessage(e);
    } finally {
        sendingId.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-xl font-semibold text-slate-800">Purchase Orders</h1>

        <form class="space-y-3 rounded border border-slate-200 bg-white p-4" @submit.prevent="submit">
            <div>
                <label for="po-supplier" class="block text-xs font-medium text-slate-600">Supplier</label>
                <select
                    id="po-supplier"
                    v-model.number="supplierId"
                    required
                    class="mt-1 rounded border border-slate-300 px-2 py-1 text-sm"
                >
                    <option :value="0" disabled>Select supplier…</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>

            <div>
                <p class="mb-1 text-xs font-medium text-slate-600">Lines</p>
                <div v-for="(line, i) in lines" :key="i" class="mb-2 flex items-end gap-2">
                    <select v-model.number="line.ingredient_id" required class="rounded border border-slate-300 px-2 py-1 text-sm">
                        <option :value="0" disabled>Select ingredient…</option>
                        <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                            {{ ing.name }} ({{ ing.unit }})
                        </option>
                    </select>
                    <input
                        v-model="line.quantity"
                        required
                        placeholder="Quantity to order"
                        class="w-40 rounded border border-slate-300 px-2 py-1 text-sm"
                    />
                    <button
                        type="button"
                        class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100"
                        :disabled="lines.length <= 1"
                        @click="removeLine(i)"
                    >
                        Remove
                    </button>
                </div>
                <button type="button" class="text-sm text-sky-600 hover:underline" @click="addLine">+ Add line</button>
            </div>

            <button
                type="submit"
                :disabled="submitting || suppliers.length === 0 || ingredients.length === 0"
                class="rounded bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
            >
                {{ submitting ? 'Creating…' : 'Create draft order' }}
            </button>
            <p v-if="suppliers.length === 0 || ingredients.length === 0" class="text-xs text-slate-400">
                Add at least one supplier and one ingredient first.
            </p>
            <p v-if="submitError" role="alert" class="text-sm text-red-600">{{ submitError }}</p>
            <p v-for="(msgs, field) in fieldErrs" :key="field" class="text-xs text-red-500">
                {{ field }}: {{ msgs.join(', ') }}
            </p>
        </form>

        <div class="flex items-center justify-between">
            <div class="flex gap-1 text-sm">
                <button
                    type="button"
                    class="rounded px-3 py-1"
                    :class="filter === 'open' ? 'bg-sky-100 text-sky-700' : 'text-slate-500 hover:bg-slate-100'"
                    @click="filter = 'open'; load()"
                >
                    Open
                </button>
                <button
                    type="button"
                    class="rounded px-3 py-1"
                    :class="filter === 'all' ? 'bg-sky-100 text-sky-700' : 'text-slate-500 hover:bg-slate-100'"
                    @click="filter = 'all'; load()"
                >
                    All
                </button>
            </div>
        </div>

        <p v-if="loadError" role="alert" class="text-sm text-red-600">{{ loadError }}</p>
        <p v-if="sendError" role="alert" class="text-sm text-red-600">{{ sendError }}</p>

        <div class="overflow-hidden rounded border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Supplier</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Created</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!loading && orders.length === 0">
                        <td colspan="5" class="px-3 py-6 text-center text-slate-400">No purchase orders found.</td>
                    </tr>
                    <tr v-for="order in orders" :key="order.id" class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ order.id }}</td>
                        <td class="px-3 py-2">{{ order.supplier_name }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ order.status_label }}</span>
                        </td>
                        <td class="px-3 py-2 text-slate-500">{{ new Date(order.created_at).toLocaleDateString() }}</td>
                        <td class="px-3 py-2 text-right">
                            <button
                                v-if="order.status === 'draft'"
                                type="button"
                                :disabled="sendingId === order.id"
                                class="mr-3 text-sky-600 hover:underline disabled:opacity-50"
                                @click="send(order)"
                            >
                                {{ sendingId === order.id ? 'Sending…' : 'Send' }}
                            </button>
                            <router-link :to="`/purchase-orders/${order.id}`" class="text-sky-600 hover:underline">
                                View →
                            </router-link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
