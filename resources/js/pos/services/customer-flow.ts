import type { OrderType } from '../types/bootstrap';

export function canManageCustomerAtPayment(type: OrderType): boolean {
    return type === 'DINE_IN' || type === 'TAKEAWAY';
}
