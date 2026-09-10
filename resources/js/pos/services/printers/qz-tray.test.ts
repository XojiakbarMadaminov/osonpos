import { describe, expect, it } from 'vitest';
import { encodeEscPos } from './qz-tray';

describe('QzTrayAdapter ESC/POS output', () => {
    it('initializes the printer and sends a full-cut command', () => {
        const output = encodeEscPos(['Receipt #0001', 'TOTAL: 65000']);

        expect(output.startsWith('\x1B\x40')).toBe(true);
        expect(output).toContain('Receipt #0001\nTOTAL: 65000');
        expect(output.endsWith('\x1D\x56\x00')).toBe(true);
    });
});
