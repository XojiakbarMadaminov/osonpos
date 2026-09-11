import type { CustomerReceipt } from './api';
import type { PrinterService } from './printer';

export interface ReceiptGateway {
    completeOrder(orderId: string): Promise<void>;
    prepareCustomerReceipt(orderId: string, reprint?: boolean): Promise<CustomerReceipt>;
}

export class ReceiptPrintService {
    constructor(
        private readonly gateway: ReceiptGateway,
        private readonly printer: PrinterService,
    ) {}

    async completeAndPrint(orderId: string): Promise<CustomerReceipt> {
        await this.gateway.completeOrder(orderId);
        return this.printCompleted(orderId);
    }

    async printCompleted(orderId: string): Promise<CustomerReceipt> {
        const receipt = await this.gateway.prepareCustomerReceipt(orderId);
        await this.printDocument(receipt);

        return receipt;
    }

    async reprint(orderId: string): Promise<CustomerReceipt> {
        const receipt = await this.gateway.prepareCustomerReceipt(orderId, true);
        await this.printDocument(receipt);

        return receipt;
    }

    private async printDocument(receipt: CustomerReceipt): Promise<void> {
        await this.printer.print(receipt.printer.system_name, {
            lines: receipt.lines,
            isReprint: receipt.is_reprint,
        });
    }
}
