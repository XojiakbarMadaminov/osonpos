import { describe, expect, it, vi } from 'vitest';
import type { KitchenRemovalTicket } from './api';
import { KitchenRemovalPrintService, type KitchenRemovalPrintGateway } from './kitchen-removal-print';
import type { PrinterService } from './printer';

const ticket: KitchenRemovalTicket = {
    order_id: '01KITCHENTEST00000000000000',
    display_number: '#0001',
    printer: { id: 1, system_name: 'Kitchen Printer', paper_width: 80 },
    removal_ids: ['01REMOVALTEST0000000000000'],
    lines: ['MAHSULOT BEKOR QILINDI', '1 x LAVASH'],
    is_reprint: false,
};

function gateway(): KitchenRemovalPrintGateway {
    return {
        prepareKitchenRemovalTicket: vi.fn(async () => ticket),
        confirmKitchenRemovalTicket: vi.fn(async () => undefined),
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

describe('KitchenRemovalPrintService', () => {
    it('confirms removals only after successful printing', async () => {
        const api = gateway();
        const output = printer();

        await new KitchenRemovalPrintService(api, output).send(ticket.order_id);

        expect(output.print).toHaveBeenCalledWith('Kitchen Printer', {
            lines: ticket.lines,
            isReprint: false,
        });
        expect(api.confirmKitchenRemovalTicket).toHaveBeenCalledWith(ticket.order_id, ticket.removal_ids);
    });

    it('keeps removals pending when printing fails', async () => {
        const api = gateway();
        const output = printer();
        output.print = vi.fn(async () => { throw new Error('Printer offline'); });

        await expect(new KitchenRemovalPrintService(api, output).send(ticket.order_id)).rejects.toThrow('Printer offline');
        expect(api.confirmKitchenRemovalTicket).not.toHaveBeenCalled();
    });
});
