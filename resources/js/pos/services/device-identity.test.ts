import { describe, expect, it } from 'vitest';
import { DeviceIdentityService } from './device-identity';
import type { StorageService } from './storage';

class MemoryStorageService implements StorageService {
    private readonly values = new Map<string, unknown>();

    get<T>(key: string): T | null {
        return (this.values.get(key) as T | undefined) ?? null;
    }

    set<T>(key: string, value: T): void {
        this.values.set(key, value);
    }

    remove(key: string): void {
        this.values.delete(key);
    }
}

describe('device identity', () => {
    it('persists a registered device and exposes its API header', () => {
        const identity = new DeviceIdentityService(new MemoryStorageService());

        expect(identity.headers()).toEqual({});

        identity.remember('01DEVICE');

        expect(identity.current()).toBe('01DEVICE');
        expect(identity.headers()).toEqual({ 'X-POS-Device-ID': '01DEVICE' });
    });
});
