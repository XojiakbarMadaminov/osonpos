export type PosPaymentMode = 'CASH' | 'CARD' | 'MIXED';

export function normalizedPaymentAmount(balance: number, value: number): number {
    if (!Number.isFinite(value)) return 0;

    return Math.min(Math.max(Math.trunc(value), 0), Math.max(balance, 0));
}

export function complementaryPaymentAmount(balance: number, value: number): number {
    return Math.max(balance - normalizedPaymentAmount(balance, value), 0);
}

export function validMixedPayment(balance: number, cashAmount: number, cardAmount: number): boolean {
    return cashAmount > 0 && cardAmount > 0 && cashAmount + cardAmount === balance;
}
