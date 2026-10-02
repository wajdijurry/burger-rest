import { ApiRequestError } from '@/api/client';

export function errorMessage(e: unknown): string {
    if (e instanceof ApiRequestError) return e.error.message;
    if (e instanceof Error) return e.message;
    return 'Something went wrong.';
}

export function fieldErrors(e: unknown): Record<string, string[]> {
    if (e instanceof ApiRequestError && e.error.fields) return e.error.fields;
    return {};
}

/**
 * The general error banner, suppressed whenever field-specific errors are
 * present. For most domain errors (e.g. a duplicate-name rejection) the
 * field message *is* the general message, so showing both duplicates the
 * same sentence; the per-field list is strictly more useful (it also shows
 * which input to fix) and anchors nearer that input.
 */
export function generalErrorMessage(e: unknown): string | null {
    return Object.keys(fieldErrors(e)).length > 0 ? null : errorMessage(e);
}
