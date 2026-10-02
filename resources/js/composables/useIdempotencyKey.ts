import { ref } from 'vue';

/**
 * One idempotency key (UUID) per *submission intent*. The key is generated
 * once and reused across retries of the same logical request (e.g. the user
 * clicks "Record delivery", it fails on a flaky network, they click again)
 * so the retry is recognised server-side as the same request. Call
 * `rotate()` only after a successful submission, or when the user discards
 * the current draft and starts a genuinely new one - never on every click.
 */
export function useIdempotencyKey() {
    const key = ref(crypto.randomUUID());

    function rotate(): void {
        key.value = crypto.randomUUID();
    }

    return { key, rotate };
}
