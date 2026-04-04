import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'assets/css/app.css',
                'assets/js/app.js',
            ],
            buildDirectory: 'build/default-theme',
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'assets'),
        },
    },
    build: {
        outDir: '../../public/themes/default-theme',
        emptyOutDir: true,
        manifest: true,
    },
});