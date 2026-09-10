import { BridgePrinterService } from './printer-core';
import { QzTrayAdapter } from './printers/qz-tray';

export type { PrintDocument, PrinterBridge, PrinterService } from './printer-core';
export { BridgePrinterService } from './printer-core';

export const printerService = new BridgePrinterService(new QzTrayAdapter());
