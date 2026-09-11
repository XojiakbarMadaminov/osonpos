import { storageService, type StorageService } from './storage';

const DEVICE_ID_KEY = 'device_id';

export class DeviceIdentityService {
    constructor(private readonly storage: StorageService = storageService) {}

    current(): string | null {
        return this.storage.get<string>(DEVICE_ID_KEY);
    }

    remember(deviceId: string): void {
        this.storage.set(DEVICE_ID_KEY, deviceId);
    }

    headers(): Record<string, string> {
        const deviceId = this.current();

        return deviceId ? { 'X-POS-Device-ID': deviceId } : {};
    }
}

export const deviceIdentityService = new DeviceIdentityService();
