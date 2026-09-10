import type { KitchenTicket } from './api';
import type { PrinterService } from './printer';

export interface KitchenPrintGateway {
    prepareKitchenTicket(orderId: string, reprint?: boolean): Promise<KitchenTicket>;
    confirmKitchenTicket(orderId: string, itemIds: string[]): Promise<void>;
}

export class KitchenPrintService {
    constructor(
        private readonly gateway: KitchenPrintGateway,
        private readonly printer: PrinterService,
    ) {}

    async send(orderId: string): Promise<KitchenTicket> {
        const ticket = await this.gateway.prepareKitchenTicket(orderId);
        await this.print(ticket);
        await this.gateway.confirmKitchenTicket(ticket.order_id, ticket.item_ids);

        return ticket;
    }

    async reprint(orderId: string): Promise<KitchenTicket> {
        const ticket = await this.gateway.prepareKitchenTicket(orderId, true);
        await this.print(ticket);

        return ticket;
    }

    private async print(ticket: KitchenTicket): Promise<void> {
        await this.printer.print(ticket.printer.system_name, {
            lines: ticket.lines,
            isReprint: ticket.is_reprint,
        });
    }
}
