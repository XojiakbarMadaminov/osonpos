import { defineStore } from 'pinia';
import type { PosBootstrap } from '../types/bootstrap';

export const useContextStore = defineStore('context', {
    state: () => ({ bootstrap: null as PosBootstrap | null }),
    actions: {
        hydrate(payload: PosBootstrap): void {
            this.bootstrap = payload;
        },
    },
});
