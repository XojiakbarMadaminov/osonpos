import type { KitchenRemovalTicket } from './api';
import type { PrinterService } from './printer';

export interface KitchenRemovalPrintGateway {
    prepareKitchenRemovalTicket(orderId: string, reprint?: boolean): Promise<KitchenRemovalTicket>;
    confirmKitchenRemovalTicket(orderId: string, removalIds: string[]): Promise<void>;
}

export class KitchenRemovalPrintService {
    constructor(
        private readonly gateway: KitchenRemovalPrintGateway,
        private readonly printer: PrinterService,
    ) {}

    async send(orderId: string): Promise<KitchenRemovalTicket> {
        const ticket = await this.gateway.prepareKitchenRemovalTicket(orderId);
        await this.print(ticket);
        await this.gateway.confirmKitchenRemovalTicket(ticket.order_id, ticket.removal_ids);

        return ticket;
    }

    async reprint(orderId: string): Promise<KitchenRemovalTicket> {
        const ticket = await this.gateway.prepareKitchenRemovalTicket(orderId, true);
        await this.print(ticket);

        return ticket;
    }

    private async print(ticket: KitchenRemovalTicket): Promise<void> {
        await this.printer.print(ticket.printer.system_name, {
            lines: ticket.lines,
            isReprint: ticket.is_reprint,
        });
    }
}
