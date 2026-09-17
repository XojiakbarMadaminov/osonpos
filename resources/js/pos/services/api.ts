import type { CartItem } from '../stores/cart';
import type { OrderType, PosBootstrap } from '../types/bootstrap';
import { deviceIdentityService, type DeviceIdentityService } from './device-identity';

export interface RegisteredDevice {
    id: string;
    name: string;
    code: string;
    store_id: number;
}

export interface ConfiguredPrinter {
    id: number;
    name: string;
    system_name: string | null;
    device_id: string | null;
    paper_width: number;
}

export interface CustomerSummary {
    id: string;
    name: string | null;
    phone: string;
}

export interface KitchenTicket {
    order_id: string;
    display_number: string;
    printer: { id: number; system_name: string; paper_width: number };
    item_ids: string[];
    lines: string[];
    is_reprint: boolean;
}

export interface KitchenRemovalTicket {
    order_id: string;
    display_number: string;
    printer: { id: number; system_name: string; paper_width: number };
    removal_ids: string[];
    lines: string[];
    is_reprint: boolean;
}

export interface OrderItemSummary {
    id: string;
    product_id: number | null;
    product_name: string;
    original_quantity: number;
    removed_quantity: number;
    quantity: number;
    unit_price: number;
    total: number;
    note: string | null;
    kitchen_printed: boolean;
}

export interface CustomerReceipt {
    order_id: string;
    printer: { id: number; system_name: string; paper_width: number };
    lines: string[];
    is_reprint: boolean;
}

export interface ShiftSummary {
    id: string;
    status: 'OPEN' | 'CLOSED';
    opening_cash: number;
    closing_cash: number | null;
    opened_at: string;
    closed_at: string | null;
    payment_totals: Record<'CASH' | 'CARD' | 'CLICK' | 'PAYME' | 'OTHER', number>;
    payments_total: number;
    cash_payments_total: number;
    expected_cash: number;
    cash_difference: number | null;
}

export interface CreatedOrder {
    id: string;
    display_number: string;
    type: OrderType;
    status: 'OPEN' | 'COMPLETED' | 'CANCELLED';
    payment_status: string;
    table_id: number | null;
    table: { id: number; name: string; number: string } | null;
    customer_id: string | null;
    customer: CustomerSummary | null;
    subtotal: number;
    delivery_fee: number;
    total: number;
    paid_amount: number;
    balance_due: number;
    unprinted_items_count: number;
    pending_item_removals_count: number;
    items?: OrderItemSummary[];
}

export class ApiService {
    constructor(private readonly deviceIdentity: DeviceIdentityService = deviceIdentityService) {}

    async bootstrap(): Promise<PosBootstrap> {
        const response = await fetch('/api/pos/bootstrap', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...this.deviceIdentity.headers() },
        });
        if (!response.ok) {
            throw new Error(response.status === 401
                ? 'Login sessiyasi tugagan. Admin panel orqali qayta kiring.'
                : 'Bu brauzer POS uchun sozlanmagan. Qurilmani bir marta ro‘yxatdan o‘tkazing.');
        }
        const payload = (await response.json()) as { data: PosBootstrap };
        this.deviceIdentity.remember(payload.data.device.id);

        return payload.data;
    }

    async activateDevice(activationCode: string): Promise<RegisteredDevice> {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch('/api/pos/devices/activate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ activation_code: activationCode }),
        });

        if (!response.ok) {
            if (response.status === 401) {
                window.location.assign('/admin/login');
                throw new Error('POS’dan foydalanish uchun avval tizimga kiring.');
            }
            if (response.status === 429) {
                throw new Error('Juda ko‘p urinish qilindi. Bir daqiqadan keyin qayta urinib ko‘ring.');
            }

            const payload = await response.json().catch(() => null) as { message?: string; errors?: { activation_code?: string[] } } | null;
            throw new Error(payload?.errors?.activation_code?.[0]
                ?? payload?.message
                ?? 'Qurilmani aktivatsiya qilib bo‘lmadi.');
        }

        const payload = (await response.json()) as { data: RegisteredDevice };
        this.deviceIdentity.remember(payload.data.id);

        return payload.data;
    }

    async printers(): Promise<ConfiguredPrinter[]> {
        const response = await fetch('/api/pos/printers', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...this.deviceIdentity.headers() },
        });

        if (!response.ok) {
            throw new Error('Sozlangan printerlarni yuklab bo‘lmadi.');
        }

        const payload = (await response.json()) as { data: ConfiguredPrinter[] };

        return payload.data;
    }

    async findCustomerByPhone(phone: string): Promise<CustomerSummary | null> {
        const response = await fetch(`/api/pos/customers/lookup?phone=${encodeURIComponent(phone)}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...this.deviceIdentity.headers() },
        });

        if (!response.ok) {
            throw new Error('Mijozni topib bo‘lmadi.');
        }

        const payload = (await response.json()) as { data: CustomerSummary | null };

        return payload.data;
    }

    async createCustomer(phone: string, name?: string): Promise<CustomerSummary> {
        const response = await this.jsonRequest('/api/pos/customers', 'POST', {
            phone,
            name: name || null,
        });
        const payload = (await response.json()) as { data: CustomerSummary };

        return payload.data;
    }

    async setOrderCustomer(orderId: string, customerId: string): Promise<CreatedOrder> {
        const response = await this.jsonRequest(`/api/pos/orders/${orderId}/customer`, 'PUT', {
            customer_id: customerId,
        });
        const payload = (await response.json()) as { data: CreatedOrder };

        return payload.data;
    }

    async removeOrderCustomer(orderId: string): Promise<CreatedOrder> {
        const response = await this.jsonRequest(`/api/pos/orders/${orderId}/customer`, 'DELETE');
        const payload = (await response.json()) as { data: CreatedOrder };

        return payload.data;
    }

    async bindPrinter(printerId: number, systemName: string): Promise<void> {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch(`/api/pos/printers/${printerId}/binding`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...this.deviceIdentity.headers(),
            },
            body: JSON.stringify({ system_name: systemName }),
        });

        if (!response.ok) {
            throw new Error('Printer biriktirilishini saqlab bo‘lmadi.');
        }
    }

    async prepareKitchenTicket(orderId: string, reprint = false): Promise<KitchenTicket> {
        const suffix = reprint ? '/reprint' : '';
        const response = await this.jsonRequest(`/api/pos/orders/${orderId}/send-kitchen${suffix}`, 'POST');
        const payload = (await response.json()) as { data: KitchenTicket };

        return payload.data;
    }

    async confirmKitchenTicket(orderId: string, itemIds: string[]): Promise<void> {
        await this.jsonRequest(`/api/pos/orders/${orderId}/send-kitchen/confirm`, 'POST', {
            item_ids: itemIds,
        });
    }

    async removeOrderItems(orderId: string, items: Array<{ id: string; order_item_id: string; quantity: number }>): Promise<CreatedOrder> {
        const response = await this.jsonRequest(`/api/pos/orders/${orderId}/item-removals`, 'POST', { items });
        const payload = (await response.json()) as { data: CreatedOrder };

        return payload.data;
    }

    async prepareKitchenRemovalTicket(orderId: string, reprint = false): Promise<KitchenRemovalTicket> {
        const suffix = reprint ? '/reprint' : '';
        const response = await this.jsonRequest(`/api/pos/orders/${orderId}/send-kitchen-removals${suffix}`, 'POST');
        const payload = (await response.json()) as { data: KitchenRemovalTicket };

        return payload.data;
    }

    async confirmKitchenRemovalTicket(orderId: string, removalIds: string[]): Promise<void> {
        await this.jsonRequest(`/api/pos/orders/${orderId}/send-kitchen-removals/confirm`, 'POST', {
            removal_ids: removalIds,
        });
    }

    async completeOrder(orderId: string): Promise<void> {
        await this.jsonRequest(`/api/pos/orders/${orderId}/complete`, 'POST');
    }

    async prepareCustomerReceipt(orderId: string, reprint = false): Promise<CustomerReceipt> {
        const suffix = reprint ? '/reprint' : '';
        const response = await this.jsonRequest(`/api/pos/orders/${orderId}/receipt${suffix}`, 'POST');
        const payload = (await response.json()) as { data: CustomerReceipt };

        return payload.data;
    }

    async currentShift(): Promise<ShiftSummary | null> {
        const response = await this.jsonRequest('/api/pos/shifts/current', 'GET');
        const payload = (await response.json()) as { data: ShiftSummary | null };

        return payload.data;
    }

    async openShift(openingCash: number): Promise<ShiftSummary> {
        const response = await this.jsonRequest('/api/pos/shifts', 'POST', { opening_cash: openingCash });
        const payload = (await response.json()) as { data: ShiftSummary };

        return payload.data;
    }

    async closeShift(shiftId: string, closingCash: number): Promise<ShiftSummary> {
        const response = await this.jsonRequest(`/api/pos/shifts/${shiftId}/close`, 'POST', {
            closing_cash: closingCash,
        });
        const payload = (await response.json()) as { data: ShiftSummary };

        return payload.data;
    }

    async createOrder(input: {
        clientId?: string;
        type: OrderType;
        tableId?: number | null;
        customerId?: string | null;
        customerPhone?: string;
        customerName?: string;
        deliveryAddress?: string;
        deliveryFee?: number;
    }): Promise<CreatedOrder> {
        const body: Record<string, unknown> = { type: input.type };
        if (input.clientId) body.id = input.clientId;
        if (input.tableId) body.table_id = input.tableId;
        if (input.customerId) body.customer_id = input.customerId;
        if (input.type === 'DELIVERY') {
            body.customer = { phone: input.customerPhone, name: input.customerName || null };
            body.delivery = { address: input.deliveryAddress, fee: input.deliveryFee ?? 0 };
        }
        const response = await this.jsonRequest('/api/pos/orders', 'POST', body);
        const payload = (await response.json()) as { data: CreatedOrder };

        return payload.data;
    }

    async addOrderItems(orderId: string, items: CartItem[]): Promise<void> {
        for (const item of items) {
            await this.jsonRequest(`/api/pos/orders/${orderId}/items`, 'POST', {
                id: item.clientId,
                product_id: item.productId,
                quantity: item.quantity,
                note: item.note || null,
            });
        }
    }

    async orders(): Promise<CreatedOrder[]> {
        const response = await fetch('/api/pos/orders', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...this.deviceIdentity.headers() },
        });
        if (!response.ok) throw new Error('Buyurtmalarni yuklab bo‘lmadi.');
        const payload = (await response.json()) as { data: CreatedOrder[] };

        return payload.data;
    }

    async order(orderId: string): Promise<CreatedOrder> {
        const response = await fetch(`/api/pos/orders/${orderId}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...this.deviceIdentity.headers() },
        });
        if (!response.ok) throw new Error('Buyurtmani yuklab bo‘lmadi.');
        const payload = (await response.json()) as { data: CreatedOrder };

        return payload.data;
    }

    async createPayment(orderId: string, method: string, amount: number, clientId?: string): Promise<void> {
        await this.jsonRequest(`/api/pos/orders/${orderId}/payments`, 'POST', { id: clientId, method, amount });
    }

    async createMixedPayment(
        orderId: string,
        cashPaymentId: string,
        cashAmount: number,
        cardPaymentId: string,
        cardAmount: number,
    ): Promise<void> {
        await this.jsonRequest(`/api/pos/orders/${orderId}/mixed-payments`, 'POST', {
            cash_payment_id: cashPaymentId,
            cash_amount: cashAmount,
            card_payment_id: cardPaymentId,
            card_amount: cardAmount,
        });
    }

    private async jsonRequest(url: string, method: string, body?: object): Promise<Response> {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...this.deviceIdentity.headers(),
            },
            body: body ? JSON.stringify(body) : undefined,
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null) as {
                message?: string;
                errors?: Record<string, string[]>;
            } | null;
            const validationMessage = payload?.errors
                ? Object.values(payload.errors).flat()[0]
                : undefined;

            throw new Error(validationMessage ?? payload?.message ?? 'So‘rovni bajarib bo‘lmadi.');
        }

        return response;
    }
}

export const apiService = new ApiService();
