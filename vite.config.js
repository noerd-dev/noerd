import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/noerd.js'],
            publicDirectory: '../../public',
            buildDirectory: 'vendor/noerd',
            hotFile: '../../public/vendor/noerd/hot',
            refresh: ['resources/views/**'],
        }),
    ],
});
