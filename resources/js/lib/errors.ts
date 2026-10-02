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
