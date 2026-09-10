import { describe, expect, it, vi } from 'vitest';
import type { CustomerReceipt } from './api';
import type { PrinterService } from './printer';
import { ReceiptPrintService, type ReceiptGateway } from './receipt-print';

const receipt: CustomerReceipt = {
    order_id: '01RECEIPTTEST0000000000000',
    printer: { id: 1, system_name: 'POS Printer', paper_width: 80 },
    lines: ['Receipt #0001', 'TOTAL: 65000'],
    is_reprint: false,
};

function gateway(): ReceiptGateway {
    return {
        completeOrder: vi.fn(async () => undefined),
        prepareCustomerReceipt: vi.fn(async () => receipt),
    };
}

function printer(): PrinterService {
    return {
        isConnected: vi.fn(() => true),
        connect: vi.fn(async () => undefined),
        discover: vi.fn(async () => []),
        print: vi.fn(async () => undefined),
        printTest: vi.fn(async () => undefined),
    };
}

describe('ReceiptPrintService', () => {
    it('completes before attempting the customer receipt', async () => {
        const api = gateway();
        const output = printer();
        const service = new ReceiptPrintService(api, output);

        await service.completeAndPrint(receipt.order_id);

        expect(api.completeOrder).toHaveBeenCalledOnce();
        expect(output.print).toHaveBeenCalledWith('POS Printer', { lines: receipt.lines, isReprint: false });
    });

    it('does not undo completion when receipt printing fails', async () => {
        const api = gateway();
        const output = printer();
        output.print = vi.fn(async () => {
            throw new Error('Printer offline');
        });
        const service = new ReceiptPrintService(api, output);

        await expect(service.completeAndPrint(receipt.order_id)).rejects.toThrow('Printer offline');
        expect(api.completeOrder).toHaveBeenCalledOnce();
    });

    it('passes the reprint marker to PrinterService', async () => {
        const api = gateway();
        api.prepareCustomerReceipt = vi.fn(async () => ({ ...receipt, is_reprint: true }));
        const output = printer();
        const service = new ReceiptPrintService(api, output);

        await service.reprint(receipt.order_id);

        expect(output.print).toHaveBeenCalledWith('POS Printer', { lines: receipt.lines, isReprint: true });
    });
});
