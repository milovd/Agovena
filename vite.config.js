import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/admin.css',
                'resources/css/installer.css',
                'themes/default/resources/css/theme.css',
                'themes/default/resources/css/admin.css',
                'resources/js/admin.js',
                'resources/js/installer.js',
                'resources/js/storefront.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '127.0.0.1',
        strictPort: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
