import { describe, expect, it } from 'vitest';
import { OnlineOnlySyncService } from './sync';
import { BrowserStorageService } from './storage';

class MemoryStorage implements Storage {
    private readonly values = new Map<string, string>();

    get length(): number {
        return this.values.size;
    }

    clear(): void {
        this.values.clear();
    }

    getItem(key: string): string | null {
        return this.values.get(key) ?? null;
    }

    key(index: number): string | null {
        return [...this.values.keys()][index] ?? null;
    }

    removeItem(key: string): void {
        this.values.delete(key);
    }

    setItem(key: string, value: string): void {
        this.values.set(key, value);
    }
}

describe('offline compatibility boundaries', () => {
    it('keeps browser persistence behind StorageService', () => {
        const memory = new MemoryStorage();
        const storage = new BrowserStorageService('test', () => memory);
        storage.set('bootstrap', { organizationId: 10 });

        expect(storage.get('bootstrap')).toEqual({ organizationId: 10 });
        storage.remove('bootstrap');
        expect(storage.get('bootstrap')).toBeNull();
    });

    it('advertises the MVP sync implementation as online-only', () => {
        expect(new OnlineOnlySyncService().status()).toEqual({ mode: 'online-only', pending: 0 });
    });
});
