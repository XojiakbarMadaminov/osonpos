export type DiscountType = 'PERCENTAGE' | 'FIXED';

export function normalizeDiscountValue(value: number | string): number {
    const parsed = Math.floor(Number(value));

    return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
}

export function calculateDiscountAmount(subtotal: number, type: DiscountType, value: number): number {
    const normalizedValue = normalizeDiscountValue(value);

    return type === 'PERCENTAGE'
        ? Math.floor(subtotal * Math.min(normalizedValue, 100) / 100)
        : Math.min(subtotal, normalizedValue);
}

export function discountValidationMessage(subtotal: number, type: DiscountType, value: number): string {
    if (value < 0) return 'Chegirma manfiy bo‘lishi mumkin emas.';
    if (type === 'PERCENTAGE' && value > 100) return 'Foiz 100 dan oshmasligi kerak.';
    if (type === 'FIXED' && value > subtotal) return 'Chegirma mahsulotlar oralig‘idan oshmasligi kerak.';

    return '';
}
