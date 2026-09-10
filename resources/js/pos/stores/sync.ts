import { defineStore } from 'pinia';

export const useSyncStore = defineStore('sync', {
    state: () => ({ pending: 0, lastSyncedAt: null as string | null }),
});
