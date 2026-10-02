<script setup lang="ts">
defineProps<{
    loading: boolean;
    error: string | null;
    lastUpdated: Date | null;
}>();

defineEmits<{ refresh: [] }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500">
        <span v-if="loading" class="inline-flex items-center gap-1.5 text-slate-600">
            <span class="h-2 w-2 animate-pulse rounded-full bg-sky-500" aria-hidden="true"></span>
            Refreshing…
        </span>
        <span v-else-if="lastUpdated">
            Updated {{ lastUpdated.toLocaleTimeString() }}
        </span>
        <span v-else>Loading…</span>

        <span v-if="error" role="alert" class="font-medium text-red-600">
            ⚠ {{ error }} — showing last known data.
        </span>

        <button
            type="button"
            class="rounded border border-slate-300 px-2 py-0.5 text-xs text-slate-600 hover:bg-slate-100"
            :disabled="loading"
            @click="$emit('refresh')"
        >
            Refresh now
        </button>
    </div>
</template>
