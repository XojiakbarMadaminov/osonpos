export interface PrintDocument {
    lines: string[];
    isReprint?: boolean;
}

export interface PrinterService {
    isConnected(): boolean;
    connect(): Promise<void>;
    discover(): Promise<string[]>;
    print(systemName: string, document: PrintDocument): Promise<void>;
    printTest(systemName: string): Promise<void>;
}

export interface PrinterBridge {
    isActive(): boolean;
    connect(): Promise<void>;
    findPrinters(): Promise<string[]>;
    print(systemName: string, lines: string[]): Promise<void>;
}

export class BridgePrinterService implements PrinterService {
    constructor(private readonly bridge: PrinterBridge) {}

    isConnected(): boolean {
        return this.bridge.isActive();
    }

    async connect(): Promise<void> {
        if (!this.bridge.isActive()) await this.bridge.connect();
    }

    async discover(): Promise<string[]> {
        await this.connect();

        return this.bridge.findPrinters();
    }

    async print(systemName: string, document: PrintDocument): Promise<void> {
        await this.connect();
        const lines = document.isReprint ? ['*** QAYTA CHOP ***', ...document.lines] : document.lines;
        await this.bridge.print(systemName, lines);
    }

    async printTest(systemName: string): Promise<void> {
        await this.print(systemName, {
            lines: ['OSONPOS SINOV CHEKI', new Date().toISOString(), 'Printer ulanishi tayyor.'],
        });
    }
}
