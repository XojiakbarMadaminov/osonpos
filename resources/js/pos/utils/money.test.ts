import { describe, expect, it } from 'vitest';
import { formatMoney } from './money';

describe('formatMoney', () => {
    it('formats integer UZS with spaces and without decimals', () => {
        expect(formatMoney(0)).toBe('0');
        expect(formatMoney(40000)).toBe('40 000');
        expect(formatMoney(1000000)).toBe('1 000 000');
        expect(formatMoney(-2500)).toBe('-2 500');
    });
});
