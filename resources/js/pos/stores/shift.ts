import { defineStore } from 'pinia';
import { apiService, type ShiftSummary } from '../services/api';

export const useShiftStore = defineStore('shift', {
    state: () => ({
        current: null as ShiftSummary | null,
        loaded: false,
        busy: false,
        error: '',
    }),
    actions: {
        async load(force = false): Promise<void> {
            if (this.loaded && !force) return;

            this.error = '';
            try {
                this.current = await apiService.currentShift();
                this.loaded = true;
            } catch (exception) {
                this.error = exception instanceof Error ? exception.message : 'Smena holatini aniqlab bo‘lmadi.';
            }
        },
        async open(openingCash: number): Promise<void> {
            this.busy = true;
            this.error = '';
            try {
                this.current = await apiService.openShift(openingCash);
                this.loaded = true;
            } catch (exception) {
                this.error = exception instanceof Error ? exception.message : 'Smenani ochib bo‘lmadi.';
            } finally {
                this.busy = false;
            }
        },
        async close(closingCash: number): Promise<void> {
            if (!this.current) return;

            this.busy = true;
            this.error = '';
            try {
                await apiService.closeShift(this.current.id, closingCash);
                this.current = null;
                this.loaded = true;
            } catch (exception) {
                this.error = exception instanceof Error ? exception.message : 'Smenani yopib bo‘lmadi.';
            } finally {
                this.busy = false;
            }
        },
    },
});
