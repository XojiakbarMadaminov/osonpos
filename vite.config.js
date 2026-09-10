import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/pos/app.ts',
                'resources/css/filament/admin/theme.css',
                'resources/css/filament/platform/theme.css',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        host: '127.0.0.1',
        allowedHosts: ['osonpos.lc'],
        hmr: {
            host: 'osonpos.lc',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
