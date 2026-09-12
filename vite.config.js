import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/search.css',
                'resources/css/reference-ui.css',
                'resources/css/reference-commerce.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
