import type { ApiErrorBody } from '@/types';

/**
 * Thrown for every non-2xx /api/v1 response. Carries the parsed
 * `{code, message, fields}` envelope so callers (forms) can show
 * field-specific errors instead of a generic toast.
 */
export class ApiRequestError extends Error {
    readonly status: number;
    readonly error: ApiErrorBody;

    constructor(status: number, error: ApiErrorBody) {
        super(error.message);
        this.name = 'ApiRequestError';
        this.status = status;
        this.error = error;
    }
}

export function isAbortError(e: unknown): boolean {
    return e instanceof DOMException && e.name === 'AbortError';
}

interface RequestOptions {
    method?: 'GET' | 'POST';
    body?: unknown;
    headers?: Record<string, string>;
    signal?: AbortSignal;
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
    const response = await fetch(`/api/v1${path}`, {
        method: options.method ?? 'GET',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...options.headers,
        },
        body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
        signal: options.signal,
    });

    // Every endpoint returns a body (even errors), so always try to parse
    // JSON rather than branching on status first.
    const json = await response.json().catch(() => null);

    if (!response.ok) {
        const error: ApiErrorBody = json?.error ?? {
            code: 'UNKNOWN_ERROR',
            message: `Request failed with HTTP ${response.status}.`,
        };
        throw new ApiRequestError(response.status, error);
    }

    return json as T;
}

export const api = {
    get: <T>(path: string, signal?: AbortSignal) => request<T>(path, { signal }),
    post: <T>(path: string, body: unknown, headers?: Record<string, string>) =>
        request<T>(path, { method: 'POST', body, headers }),
};
