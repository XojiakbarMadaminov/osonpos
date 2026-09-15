import { describe, expect, it } from 'vitest';
import { formatUzbekPhone, nationalPhoneDigits, uzbekPhone } from './customer';

describe('Uzbek customer phone helpers', () => {
    it('normalizes formatted and international input', () => {
        expect(nationalPhoneDigits('+998 90 123-45-67')).toBe('901234567');
        expect(uzbekPhone('90 123 45 67')).toBe('+998901234567');
    });

    it('requires all nine national digits', () => {
        expect(uzbekPhone('90123')).toBe('');
    });

    it('formats a normalized number for compact display', () => {
        expect(formatUzbekPhone('+998901234567')).toBe('+998 90 123 45 67');
    });
});
