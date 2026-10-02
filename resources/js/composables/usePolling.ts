import { onMounted, onUnmounted, ref, type Ref } from 'vue';
import { isAbortError } from '@/api/client';

export interface Polling<T> {
    data: Ref<T | null>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    lastUpdated: Ref<Date | null>;
    refresh: () => Promise<void>;
}

/**
 * Freshness contract used by every data-driven page (dashboard, purchase
 * orders, PO detail): fetch once on mount, poll every `intervalMs` while the
 * tab is visible, refetch immediately when the tab becomes visible again,
 * and expose `refresh()` so callers can force an update right after an
 * action that changed server state (e.g. a sale or a delivery), including
 * on an idempotent *replay* - the UI must reflect reality, not just "did my
 * request succeed".
 *
 * At most one request is ever in flight: every call to `refresh()` aborts
 * whatever request preceded it before starting a new one, so a slow poll
 * response can never silently overwrite data from a newer refresh (and the
 * timer never piles up overlapping requests either).
 */
export function usePolling<T>(fetcher: (signal: AbortSignal) => Promise<T>, intervalMs = 5000): Polling<T> {
    const data = ref<T | null>(null) as Ref<T | null>;
    const loading = ref(false);
    const error = ref<string | null>(null);
    const lastUpdated = ref<Date | null>(null);

    let controller: AbortController | null = null;
    let timer: ReturnType<typeof setInterval> | null = null;

    async function refresh(): Promise<void> {
        controller?.abort();
        const mine = new AbortController();
        controller = mine;
        loading.value = true;

        try {
            const result = await fetcher(mine.signal);
            if (mine.signal.aborted) {
                return; // superseded by a newer refresh; discard this result
            }
            data.value = result;
            lastUpdated.value = new Date();
            error.value = null;
        } catch (e) {
            if (isAbortError(e) || mine.signal.aborted) {
                return;
            }
            error.value = e instanceof Error ? e.message : 'Failed to load.';
        } finally {
            if (controller === mine) {
                loading.value = false;
            }
        }
    }

    function onVisibilityChange(): void {
        if (document.visibilityState === 'visible') {
            void refresh();
        }
    }

    onMounted(() => {
        void refresh();
        timer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                void refresh();
            }
        }, intervalMs);
        document.addEventListener('visibilitychange', onVisibilityChange);
    });

    onUnmounted(() => {
        if (timer) clearInterval(timer);
        controller?.abort();
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    return { data, loading, error, lastUpdated, refresh };
}
