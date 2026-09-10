import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null as { id: number; name: string; email: string } | null,
        permissions: [] as string[],
    }),
    actions: {
        hydrate(user: { id: number; name: string; email: string }, permissions: string[]): void {
            this.user = user;
            this.permissions = permissions;
        },
        can(permission: string): boolean {
            return this.permissions.includes(permission);
        },
    },
});
