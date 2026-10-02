import { api } from './client';
import type {
    Delivery,
    DashboardSnapshot,
    Ingredient,
    MenuItem,
    PurchaseOrder,
    Sale,
    StockRow,
    Supplier,
} from '@/types';

interface Collection<T> {
    data: T[];
}
interface Single<T> {
    data: T;
}

export interface LineInput {
    ingredient_id: number;
    quantity: string;
}

export interface DeliveryLineInput {
    purchase_order_line_id: number;
    quantity: string;
}

export interface DeliveryResult {
    delivery: Delivery;
    purchase_order: PurchaseOrder;
    replayed: boolean;
}

export interface SaleResult {
    sale: Sale;
    replayed: boolean;
}

export function listIngredients(signal?: AbortSignal): Promise<Ingredient[]> {
    return api.get<Collection<Ingredient>>('/ingredients', signal).then((r) => r.data);
}

export function createIngredient(name: string, unit: string): Promise<Ingredient> {
    return api.post<Single<Ingredient>>('/ingredients', { name, unit }).then((r) => r.data);
}

export function listSuppliers(signal?: AbortSignal): Promise<Supplier[]> {
    return api.get<Collection<Supplier>>('/suppliers', signal).then((r) => r.data);
}

export function createSupplier(name: string): Promise<Supplier> {
    return api.post<Single<Supplier>>('/suppliers', { name }).then((r) => r.data);
}

export function listMenuItems(signal?: AbortSignal): Promise<MenuItem[]> {
    return api.get<Collection<MenuItem>>('/menu-items', signal).then((r) => r.data);
}

export function createMenuItem(name: string, lines: LineInput[]): Promise<MenuItem> {
    return api.post<Single<MenuItem>>('/menu-items', { name, lines }).then((r) => r.data);
}

export function listPurchaseOrders(status?: string, signal?: AbortSignal): Promise<PurchaseOrder[]> {
    const qs = status ? `?status=${encodeURIComponent(status)}` : '';
    return api.get<Collection<PurchaseOrder>>(`/purchase-orders${qs}`, signal).then((r) => r.data);
}

export function getPurchaseOrder(id: number, signal?: AbortSignal): Promise<PurchaseOrder> {
    return api.get<Single<PurchaseOrder>>(`/purchase-orders/${id}`, signal).then((r) => r.data);
}

export function createPurchaseOrder(supplierId: number, lines: LineInput[]): Promise<PurchaseOrder> {
    return api
        .post<Single<PurchaseOrder>>('/purchase-orders', { supplier_id: supplierId, lines })
        .then((r) => r.data);
}

export function sendPurchaseOrder(id: number): Promise<PurchaseOrder> {
    return api.post<Single<PurchaseOrder>>(`/purchase-orders/${id}/send`, {}).then((r) => r.data);
}

export function receiveDelivery(
    orderId: number,
    idempotencyKey: string,
    lines: DeliveryLineInput[],
): Promise<DeliveryResult> {
    return api.post<DeliveryResult>(`/purchase-orders/${orderId}/deliveries`, { lines }, {
        'Idempotency-Key': idempotencyKey,
    });
}

export function recordSale(eventId: string, menuItemId: number, quantity: number): Promise<SaleResult> {
    return api.post<SaleResult>('/sales', { event_id: eventId, menu_item_id: menuItemId, quantity });
}

export function getStock(signal?: AbortSignal): Promise<StockRow[]> {
    return api.get<Collection<StockRow>>('/stock', signal).then((r) => r.data);
}

export function getDashboard(signal?: AbortSignal): Promise<DashboardSnapshot> {
    return api.get<DashboardSnapshot>('/dashboard', signal);
}
