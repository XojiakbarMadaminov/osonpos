export interface StorageService {
    get<T>(key: string): T | null;
    set<T>(key: string, value: T): void;
    remove(key: string): void;
}

export class BrowserStorageService implements StorageService {
    constructor(
        private readonly namespace = 'osonpos',
        private readonly storageProvider: () => Storage = () => window.localStorage,
    ) {}

    get<T>(key: string): T | null {
        const value = this.storageProvider().getItem(this.key(key));

        return value === null ? null : JSON.parse(value) as T;
    }

    set<T>(key: string, value: T): void {
        this.storageProvider().setItem(this.key(key), JSON.stringify(value));
    }

    remove(key: string): void {
        this.storageProvider().removeItem(this.key(key));
    }

    private key(key: string): string {
        return `${this.namespace}:${key}`;
    }
}

export const storageService: StorageService = new BrowserStorageService();
