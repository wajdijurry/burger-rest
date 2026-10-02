<script setup lang="ts">
const nav = [
    { to: '/', label: 'Dashboard' },
    { to: '/ingredients', label: 'Ingredients' },
    { to: '/suppliers', label: 'Suppliers' },
    { to: '/menu-items', label: 'Menu Items' },
    { to: '/purchase-orders', label: 'Purchase Orders' },
    { to: '/pos', label: 'POS Simulator' },
];
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-1 px-4 py-3">
                <span class="mr-4 font-semibold text-slate-800">🍔 Foodics Burger Co.</span>
                <nav class="flex flex-wrap gap-1" aria-label="Main navigation">
                    <router-link
                        v-for="item in nav"
                        :key="item.to"
                        :to="item.to"
                        class="rounded px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
                        active-class="bg-sky-100 text-sky-700"
                        exact-active-class="bg-sky-100 text-sky-700"
                    >
                        {{ item.label }}
                    </router-link>
                </nav>
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-6">
            <router-view />
        </main>
    </div>
</template>
