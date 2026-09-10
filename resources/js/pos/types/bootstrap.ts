import type { ConfiguredPrinter, ShiftSummary } from '../services/api';

export interface PosCategory {
    id: number;
    name: string;
    sort_order: number;
}

export interface PosProduct {
    id: number;
    category_id: number;
    name: string;
    price: number;
}

export interface PosTable {
    id: number;
    name: string;
    number: string;
    capacity: number | null;
    is_occupied: boolean;
}

export interface PosBootstrap {
    organization: { id: number; name: string; slug: string };
    store: { id: number; name: string; address: string | null; timezone: string };
    device: { id: string; name: string; code: string };
    user: { id: number; name: string; email: string };
    permissions: string[];
    features: string[];
    categories: PosCategory[];
    products: PosProduct[];
    tables: PosTable[];
    printers: ConfiguredPrinter[];
    print_routes: Array<{ id: number; print_type: string; printer_id: number }>;
    active_shift: ShiftSummary | null;
}

export type OrderType = 'DINE_IN' | 'TAKEAWAY' | 'DELIVERY';
