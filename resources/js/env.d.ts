/// <reference types="vite/client" />

declare module 'qz-tray' {
    const qz: unknown;
    export default qz;
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    const component: DefineComponent<Record<string, never>, Record<string, never>, unknown>;
    export default component;
}
