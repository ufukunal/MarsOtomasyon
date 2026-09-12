import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/search.css',
                'resources/css/sales-order-product-search.css',
                'resources/js/sales-order-product-search.js',
            ],
            refresh: true,
        }),
    ],
});
