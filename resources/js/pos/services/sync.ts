export interface SyncStatus {
    mode: 'online-only';
    pending: number;
}

export interface SyncService {
    status(): SyncStatus;
}

export class OnlineOnlySyncService implements SyncService {
    status(): SyncStatus {
        return { mode: 'online-only', pending: 0 };
    }
}

export const syncService: SyncService = new OnlineOnlySyncService();
