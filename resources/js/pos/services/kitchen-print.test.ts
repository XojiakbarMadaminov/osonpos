import { describe, expect, it, vi } from 'vitest';
import type { KitchenTicket } from './api';
import { KitchenPrintService, type KitchenPrintGateway } from './kitchen-print';
import type { PrinterService } from './printer';

const ticket: KitchenTicket = {
    order_id: '01KITCHENTEST00000000000000',
    display_number: '#0001',
    printer: { id: 1, system_name: 'Kitchen Printer', paper_width: 80 },
    item_ids: ['01ITEMTEST00000000000000000'],
    lines: ['Kitchen #0001', '1x Cola'],
    is_reprint: false,
};

function gateway(): KitchenPrintGateway {
    return {
        prepareKitchenTicket: vi.fn(async () => ticket),
        confirmKitchenTicket: vi.fn(async () => undefined),
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

describe('KitchenPrintService', () => {
    it('confirms items only after successful printing', async () => {
        const api = gateway();
        const output = printer();
        const service = new KitchenPrintService(api, output);

        await service.send(ticket.order_id);

        expect(output.print).toHaveBeenCalledWith('Kitchen Printer', {
            lines: ticket.lines,
            isReprint: false,
        });
        expect(api.confirmKitchenTicket).toHaveBeenCalledWith(ticket.order_id, ticket.item_ids);
    });

    it('does not confirm items when printing fails', async () => {
        const api = gateway();
        const output = printer();
        output.print = vi.fn(async () => {
            throw new Error('Printer offline');
        });
        const service = new KitchenPrintService(api, output);

        await expect(service.send(ticket.order_id)).rejects.toThrow('Printer offline');
        expect(api.confirmKitchenTicket).not.toHaveBeenCalled();
    });

    it('prints reprints without changing item print state', async () => {
        const reprintTicket = { ...ticket, is_reprint: true };
        const api = gateway();
        api.prepareKitchenTicket = vi.fn(async () => reprintTicket);
        const output = printer();
        const service = new KitchenPrintService(api, output);

        await service.reprint(ticket.order_id);

        expect(output.print).toHaveBeenCalledWith('Kitchen Printer', {
            lines: ticket.lines,
            isReprint: true,
        });
        expect(api.confirmKitchenTicket).not.toHaveBeenCalled();
    });
});
