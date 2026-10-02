<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { getPurchaseOrder, receiveDelivery, sendPurchaseOrder } from '@/api/resources';
import { usePolling } from '@/composables/usePolling';
import { useIdempotencyKey } from '@/composables/useIdempotencyKey';
import { errorMessage, fieldErrors } from '@/lib/errors';
import FreshnessBar from '@/components/FreshnessBar.vue';
import type { PurchaseOrder } from '@/types';

const props = defineProps<{ id: number }>();

const { data: order, loading, error, lastUpdated, refresh } = usePolling<PurchaseOrder>(
    (signal) => getPurchaseOrder(props.id, signal),
    5000,
);

// Quantity to receive now, keyed by purchase_order_line_id. Reset whenever
// the order identity changes or a delivery is successfully recorded, but
// deliberately NOT on every background poll refresh (that would wipe out
// what the user is actively typing).
const receiveQty = reactive<Record<number, string>>({});

watch(
    () => props.id,
    () => {
        for (const k of Object.keys(receiveQty)) delete receiveQty[Number(k)];
    },
);

const sending = ref(false);
const sendError = ref<string | null>(null);

const { key: idempotencyKey, rotate: rotateKey } = useIdempotencyKey();
const receiving = ref(false);
const receiveError = ref<string | null>(null);
const receiveFieldErrors = ref<Record<string, string[]>>({});
const lastResult = ref<'created' | 'replayed' | null>(null);

const outstandingLines = computed(() => (order.value?.lines ?? []).filter((l) => Number(l.quantity_outstanding) > 0));

async function send() {
    if (sending.value || !order.value) return;
    sending.value = true;
    sendError.value = null;
    try {
        await sendPurchaseOrder(order.value.id);
        await refresh();
    } catch (e) {
        sendError.value = errorMessage(e);
    } finally {
        sending.value = false;
    }
}

async function submitDelivery() {
    if (receiving.value || !order.value) return;

    const lines = Object.entries(receiveQty)
        .filter(([, qty]) => qty.trim() !== '')
        .map(([lineId, qty]) => ({ purchase_order_line_id: Number(lineId), quantity: qty.trim() }));

    if (lines.length === 0) {
        receiveError.value = 'Enter a received quantity for at least one line.';
        return;
    }

    receiving.value = true;
    receiveError.value = null;
    receiveFieldErrors.value = {};
    try {
        const result = await receiveDelivery(order.value.id, idempotencyKey.value, lines);
        lastResult.value = result.replayed ? 'replayed' : 'created';
        for (const k of Object.keys(receiveQty)) delete receiveQty[Number(k)];
        rotateKey(); // this submission succeeded; the next click is a new one
        await refresh();
    } catch (e) {
        receiveError.value = errorMessage(e);
        receiveFieldErrors.value = fieldErrors(e);
        // Deliberately do NOT rotate the key here: a retry of this exact
        // click should reuse the same Idempotency-Key.
    } finally {
        receiving.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-800">
                Purchase Order #{{ id }}
                <span v-if="order" class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-sm font-normal">
                    {{ order.status_label }}
                </span>
            </h1>
            <FreshnessBar :loading="loading" :error="error" :last-updated="lastUpdated" @refresh="refresh" />
        </div>

        <router-link to="/purchase-orders" class="text-sm text-sky-600 hover:underline">← Back to all orders</router-link>

        <template v-if="order">
            <section class="rounded border border-slate-200 bg-white p-4">
                <dl class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-slate-500">Supplier</dt>
                        <dd class="font-medium">{{ order.supplier_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Created</dt>
                        <dd>{{ new Date(order.created_at).toLocaleString() }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Sent</dt>
                        <dd>{{ order.sent_at ? new Date(order.sent_at).toLocaleString() : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Closed</dt>
                        <dd>{{ order.closed_at ? new Date(order.closed_at).toLocaleString() : '—' }}</dd>
                    </div>
                </dl>

                <button
                    v-if="order.status === 'draft'"
                    type="button"
                    :disabled="sending"
                    class="mt-4 rounded bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
                    @click="send"
                >
                    {{ sending ? 'Sending…' : 'Send to supplier' }}
                </button>
                <p v-if="sendError" role="alert" class="mt-2 text-sm text-red-600">{{ sendError }}</p>
            </section>

            <section>
                <h2 class="mb-2 font-medium text-slate-700">Lines</h2>
                <div class="overflow-hidden rounded border border-slate-200 bg-white">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Ingredient</th>
                                <th class="px-3 py-2 text-right">Ordered</th>
                                <th class="px-3 py-2 text-right">Received</th>
                                <th class="px-3 py-2 text-right">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in order.lines" :key="line.id" class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ line.ingredient_name }} ({{ line.unit }})</td>
                                <td class="px-3 py-2 text-right font-mono">{{ line.quantity_ordered }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ line.quantity_received }}</td>
                                <td
                                    class="px-3 py-2 text-right font-mono"
                                    :class="Number(line.quantity_outstanding) > 0 ? 'text-amber-700' : 'text-emerald-700'"
                                >
                                    {{ line.quantity_outstanding }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="order.status === 'sent' || order.status === 'received'">
                <h2 class="mb-2 font-medium text-slate-700">Record a delivery</h2>
                <form class="space-y-3 rounded border border-slate-200 bg-white p-4" @submit.prevent="submitDelivery">
                    <div v-if="outstandingLines.length === 0" class="text-sm text-slate-400">
                        Nothing outstanding — this order is fully received.
                    </div>
                    <div v-for="line in outstandingLines" :key="line.id" class="flex items-center gap-3">
                        <label :for="`recv-${line.id}`" class="w-56 text-sm text-slate-700">
                            {{ line.ingredient_name }}
                            <span class="text-slate-400">(outstanding {{ line.quantity_outstanding }} {{ line.unit }})</span>
                        </label>
                        <input
                            :id="`recv-${line.id}`"
                            v-model="receiveQty[line.id]"
                            placeholder="0"
                            class="w-32 rounded border border-slate-300 px-2 py-1 text-sm"
                        />
                    </div>
                    <button
                        v-if="outstandingLines.length > 0"
                        type="submit"
                        :disabled="receiving"
                        class="rounded bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50"
                    >
                        {{ receiving ? 'Recording…' : 'Record delivery' }}
                    </button>
                    <p v-if="receiveError" role="alert" class="text-sm text-red-600">{{ receiveError }}</p>
                    <p v-for="(msgs, field) in receiveFieldErrors" :key="field" class="text-xs text-red-500">
                        {{ field }}: {{ msgs.join(', ') }}
                    </p>
                    <p v-if="lastResult === 'replayed'" class="text-xs text-slate-400">
                        That delivery was already recorded — this click was recognised as a safe retry (idempotent
                        replay), nothing was double-counted.
                    </p>
                </form>
            </section>

            <section v-if="order.deliveries.length > 0">
                <h2 class="mb-2 font-medium text-slate-700">Delivery history</h2>
                <div class="space-y-2">
                    <div v-for="d in order.deliveries" :key="d.id" class="rounded border border-slate-200 bg-white p-3 text-sm">
                        <p class="mb-1 text-slate-500">{{ new Date(d.created_at).toLocaleString() }}</p>
                        <ul class="space-y-0.5">
                            <li v-for="dl in d.lines" :key="dl.id">
                                {{ dl.quantity_received }} {{ dl.unit }} — {{ dl.ingredient_name }}
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </template>

        <p v-else-if="!loading" class="text-sm text-slate-400">Order not found.</p>
    </div>
</template>
