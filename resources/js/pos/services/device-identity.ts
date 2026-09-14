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
        return {};
    }
}

export const deviceIdentityService = new DeviceIdentityService();
