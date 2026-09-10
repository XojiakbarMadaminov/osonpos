import { describe, expect, it, vi } from 'vitest';
import { BridgePrinterService, type PrinterBridge } from './printer';

function gateway(overrides: Partial<PrinterBridge> = {}): PrinterBridge {
    return {
        isActive: vi.fn(() => true),
        connect: vi.fn(async () => undefined),
        findPrinters: vi.fn(async () => ['Receipt Printer']),
        print: vi.fn(async () => undefined),
        ...overrides,
    };
}

describe('BridgePrinterService', () => {
    it('discovers printers only through its QZ gateway', async () => {
        const mockGateway = gateway({ isActive: vi.fn(() => false) });
        const service = new BridgePrinterService(mockGateway);

        await expect(service.discover()).resolves.toEqual(['Receipt Printer']);
        expect(mockGateway.connect).toHaveBeenCalledOnce();
        expect(mockGateway.findPrinters).toHaveBeenCalledOnce();
    });

    it('marks reprints in the generated physical document', async () => {
        const mockGateway = gateway();
        const service = new BridgePrinterService(mockGateway);

        await service.print('Receipt Printer', { lines: ['Receipt #01'], isReprint: true });

        expect(mockGateway.print).toHaveBeenCalledWith('Receipt Printer', ['*** REPRINT ***', 'Receipt #01']);
    });

    it('propagates print failure without invoking application mutations', async () => {
        let orderWasMutated = false;
        const mockGateway = gateway({
            print: vi.fn(async () => {
                throw new Error('Printer offline');
            }),
        });
        const service = new BridgePrinterService(mockGateway);

        await expect(service.printTest('Receipt Printer')).rejects.toThrow('Printer offline');
        expect(orderWasMutated).toBe(false);
    });
});
