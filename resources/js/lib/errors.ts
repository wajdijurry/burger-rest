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

const FIELD_LABELS: Record<string, string> = {
    name: 'Name',
    unit: 'Unit',
    supplier_id: 'Supplier',
    menu_item_id: 'Menu item',
    event_id: 'Sale event',
    quantity: 'Quantity',
    lines: 'Lines',
    idempotency_key: 'Idempotency key',
};

/**
 * Turn API field keys (`supplier_id`, `lines.0.ingredient_id`) into labels
 * suitable for the UI, so managers never see raw request/JSON paths.
 */
export function formatFieldLabel(field: string): string {
    const lineMatch = field.match(/^lines\.(\d+)\.(.+)$/);
    if (lineMatch) {
        const lineNumber = Number(lineMatch[1]) + 1;
        const sub = lineMatch[2];
        const subLabel =
            sub === 'ingredient_id'
                ? 'ingredient'
                : sub === 'purchase_order_line_id'
                  ? 'order line'
                  : sub === 'quantity'
                    ? 'quantity'
                    : sub.replaceAll('_', ' ');

        return `Line ${lineNumber} ${subLabel}`;
    }

    return FIELD_LABELS[field] ?? field.replaceAll('_', ' ');
}
