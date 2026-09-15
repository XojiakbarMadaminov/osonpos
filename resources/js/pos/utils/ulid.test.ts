import { describe, expect, it } from 'vitest';
import { generateUlid } from './ulid';

describe('generateUlid', () => {
    it('creates a 26-character Crockford identifier with a sortable time prefix', () => {
        const first = generateUlid(1_000);
        const second = generateUlid(2_000);

        expect(first).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
        expect(second.slice(0, 10) > first.slice(0, 10)).toBe(true);
    });
});
