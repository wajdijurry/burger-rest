<script setup lang="ts">
import { computed } from 'vue';
import { getDashboard } from '@/api/resources';
import { usePolling } from '@/composables/usePolling';
import FreshnessBar from '@/components/FreshnessBar.vue';
import type { DashboardSnapshot } from '@/types';

const { data, loading, error, lastUpdated, refresh } = usePolling<DashboardSnapshot>(
    (signal) => getDashboard(signal),
    5000,
);

const stock = computed(() => data.value?.stock ?? []);
const openOrders = computed(() => data.value?.open_orders ?? []);
const negativeCount = computed(() => stock.value.filter((row) => row.is_negative).length);

function outstandingLineCount(order: DashboardSnapshot['open_orders'][number]): number {
    return order.lines.filter((l) => Number(l.quantity_outstanding) > 0).length;
}
</script>

<template>
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-800">Dashboard</h1>
            <FreshnessBar :loading="loading" :error="error" :last-updated="lastUpdated" @refresh="refresh" />
        </div>

        <p class="text-xs text-slate-400" v-if="data">
            Snapshot generated at {{ new Date(data.generated_at).toLocaleTimeString() }}. This page polls every 5s
            and refreshes when this tab regains focus, but numbers can still be a few seconds old - treat them as
            "recent", not "live".
        </p>

        <section aria-labelledby="stock-heading">
            <div class="mb-2 flex items-center gap-3">
                <h2 id="stock-heading" class="font-medium text-slate-700">Current stock</h2>
                <span v-if="negativeCount > 0" class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                    {{ negativeCount }} ingredient{{ negativeCount === 1 ? '' : 's' }} below zero
                </span>
            </div>

            <div class="overflow-hidden rounded border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Ingredient</th>
                            <th class="px-3 py-2">Unit</th>
                            <th class="px-3 py-2 text-right">On hand</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="stock.length === 0 && !loading">
                            <td colspan="3" class="px-3 py-6 text-center text-slate-400">
                                No ingredients yet. Add some on the Ingredients page.
                            </td>
                        </tr>
                        <tr v-for="row in stock" :key="row.ingredient_id" class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ row.name }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ row.unit }}</td>
                            <td
                                class="px-3 py-2 text-right font-mono"
                                :class="row.is_negative ? 'font-semibold text-red-600' : 'text-slate-800'"
                            >
                                {{ row.quantity }}
                                <span v-if="row.is_negative" title="Negative stock">⚠</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="orders-heading">
            <h2 id="orders-heading" class="mb-2 font-medium text-slate-700">Open purchase orders</h2>
            <div class="overflow-hidden rounded border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Supplier</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Lines outstanding</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="openOrders.length === 0 && !loading">
                            <td colspan="5" class="px-3 py-6 text-center text-slate-400">
                                No open purchase orders right now.
                            </td>
                        </tr>
                        <tr v-for="order in openOrders" :key="order.id" class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ order.id }}</td>
                            <td class="px-3 py-2">{{ order.supplier_name }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ order.status_label }}</span>
                            </td>
                            <td class="px-3 py-2">{{ outstandingLineCount(order) }} / {{ order.lines.length }}</td>
                            <td class="px-3 py-2 text-right">
                                <router-link
                                    :to="`/purchase-orders/${order.id}`"
                                    class="text-sky-600 hover:underline"
                                >
                                    View →
                                </router-link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
