import { describe, expect, it } from 'vitest';
import { encodeEscPos } from './qz-tray';

describe('QzTrayAdapter ESC/POS output', () => {
    it('resets printer state, feeds beyond the cutter and separates every job', () => {
        const output = encodeEscPos(['Receipt #0001', 'TOTAL: 65000']);

        expect(output.startsWith('\x1B\x40\x1B\x61\x00\x1B\x21\x00')).toBe(true);
        expect(output).toContain('Receipt #0001\nTOTAL: 65000');
        expect(output.endsWith('\n\x1B\x64\x06\x1D\x56\x00')).toBe(true);
    });
});
