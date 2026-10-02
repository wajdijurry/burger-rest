// Mirrors app/Http/Resources/*.php exactly. Quantities are always strings
// (never JS numbers) end-to-end - they are exact-decimal values produced by
// the backend's BCMath-backed Quantity value object, and parsing them into
// a float on the client would reintroduce the precision problems the whole
// backend exists to avoid. The UI only ever displays these strings or sends
// back user-typed strings; it never does arithmetic on them itself.

export interface Ingredient {
    id: number;
    name: string;
    unit: string;
    created_at: string;
}

export interface Supplier {
    id: number;
    name: string;
    created_at: string;
}

export interface RecipeLine {
    id: number;
    ingredient_id: number;
    ingredient_name: string;
    unit: string;
    quantity: string;
}

export interface MenuItem {
    id: number;
    name: string;
    recipe_lines: RecipeLine[];
    created_at: string;
}

export interface PurchaseOrderLine {
    id: number;
    ingredient_id: number;
    ingredient_name: string;
    unit: string;
    quantity_ordered: string;
    quantity_received: string;
    quantity_outstanding: string;
}

export interface DeliveryLine {
    id: number;
    purchase_order_line_id: number;
    ingredient_name: string;
    unit: string;
    quantity_received: string;
}

export interface Delivery {
    id: number;
    purchase_order_id: number;
    lines: DeliveryLine[];
    created_at: string;
}

export type PurchaseOrderStatusValue = 'draft' | 'sent' | 'received' | 'closed';

export interface PurchaseOrder {
    id: number;
    status: PurchaseOrderStatusValue;
    status_label: string;
    supplier_id: number;
    supplier_name?: string;
    sent_at: string | null;
    closed_at: string | null;
    created_at: string;
    lines: PurchaseOrderLine[];
    deliveries: Delivery[];
}

export interface Sale {
    event_id: string;
    menu_item_id: number;
    quantity: number;
    created_at: string;
}

export interface StockRow {
    ingredient_id: number;
    name: string;
    unit: string;
    quantity: string;
    is_negative: boolean;
}

export interface DashboardSnapshot {
    stock: StockRow[];
    open_orders: PurchaseOrder[];
    generated_at: string;
}

/** The {"error": {...}} envelope every non-2xx /api/v1 response uses. */
export interface ApiErrorBody {
    code: string;
    message: string;
    fields?: Record<string, string[]>;
}
