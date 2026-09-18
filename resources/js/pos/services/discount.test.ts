import { describe, expect, it } from 'vitest';
import { calculateDiscountAmount, discountValidationMessage, normalizeDiscountValue } from './discount';

describe('discount calculation', () => {
    it('calculates integer percentage and fixed discounts', () => {
        expect(calculateDiscountAmount(100_000, 'PERCENTAGE', 10)).toBe(10_000);
        expect(calculateDiscountAmount(99_999, 'PERCENTAGE', 10)).toBe(9_999);
        expect(calculateDiscountAmount(100_000, 'FIXED', 15_000)).toBe(15_000);
    });

    it('normalizes input and caps preview values safely', () => {
        expect(normalizeDiscountValue('12.9')).toBe(12);
        expect(normalizeDiscountValue('')).toBe(0);
        expect(calculateDiscountAmount(100_000, 'PERCENTAGE', 120)).toBe(100_000);
        expect(calculateDiscountAmount(100_000, 'FIXED', 120_000)).toBe(100_000);
    });

    it('returns clear Uzbek validation messages', () => {
        expect(discountValidationMessage(100_000, 'PERCENTAGE', 101)).toBe('Foiz 100 dan oshmasligi kerak.');
        expect(discountValidationMessage(100_000, 'FIXED', 100_001)).toBe('Chegirma mahsulotlar oralig‘idan oshmasligi kerak.');
        expect(discountValidationMessage(100_000, 'PERCENTAGE', 10)).toBe('');
    });
});
