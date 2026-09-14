import qz from 'qz-tray';
import { deviceIdentityService } from '../device-identity';
import type { PrinterBridge } from '../printer-core';

type QzApi = {
    websocket: { isActive(): boolean; connect(options?: { retries?: number; delay?: number }): Promise<void> };
    printers: { find(): Promise<string[]> };
    configs: { create(name: string): unknown };
    security: {
        setCertificatePromise(resolver: () => Promise<string>): void;
        setSignatureAlgorithm(algorithm: string): void;
        setSignaturePromise(resolver: (data: string) => (resolve: (signature: string) => void, reject: (error: unknown) => void) => void): void;
    };
    print(config: unknown, data: Array<{ type: string; format: string; data: string }>): Promise<void>;
};

export class QzTrayAdapter implements PrinterBridge {
    private readonly qz = qz as unknown as QzApi;
    private securityConfigured = false;

    isActive(): boolean {
        return this.qz.websocket.isActive();
    }

    async connect(): Promise<void> {
        this.configureSigning();
        await this.qz.websocket.connect({ retries: 5, delay: 2 });
    }

    async findPrinters(): Promise<string[]> {
        return this.qz.printers.find();
    }

    async print(systemName: string, lines: string[]): Promise<void> {
        await this.qz.print(this.qz.configs.create(systemName), [{
            type: 'raw',
            format: 'plain',
            data: encodeEscPos(lines),
        }]);
    }

    private configureSigning(): void {
        if (this.securityConfigured || import.meta.env.VITE_QZ_SIGNED_PRINTING !== 'true') return;
        this.qz.security.setCertificatePromise(async () => {
            const response = await fetch('/api/pos/qz/certificate', {
                credentials: 'same-origin',
                headers: deviceIdentityService.headers(),
            });
            if (!response.ok) throw new Error('QZ sertifikatini yuklab bo‘lmadi.');

            return response.text();
        });
        this.qz.security.setSignatureAlgorithm('SHA512');
        this.qz.security.setSignaturePromise((data: string) => (resolve, reject) => {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            fetch('/api/pos/qz/sign', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    ...deviceIdentityService.headers(),
                },
                body: JSON.stringify({ data }),
            }).then((response) => {
                if (!response.ok) throw new Error('QZ imzosini yaratib bo‘lmadi.');
                return response.text();
            }).then(resolve).catch(reject);
        });
        this.securityConfigured = true;
    }
}

export function encodeEscPos(lines: string[]): string {
    const initialize = '\x1B\x40';
    const alignLeft = '\x1B\x61\x00';
    const normalText = '\x1B\x21\x00';
    const feedSixLines = '\x1B\x64\x06';
    const fullCut = '\x1D\x56\x00';

    return `${initialize}${alignLeft}${normalText}${lines.join('\n')}\n${feedSixLines}${fullCut}`;
}
