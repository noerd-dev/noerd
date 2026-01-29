import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/noerd.js'],
            publicDirectory: 'dist',
            buildDirectory: 'build',
            hotFile: '../../public/vendor/noerd/hot',
            refresh: ['resources/views/**'],
        }),
    ],
});
