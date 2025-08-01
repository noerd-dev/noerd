import { defineConfig } from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        tailwindcss(),
    ],
    build: {
        outDir: 'dist',
        rollupOptions: {
            input: {
                'noerd': 'resources/js/noerd.js'
            },
            output: {
                entryFileNames: 'noerd.js',
                assetFileNames: 'noerd.css'
            }
        },
        target: 'es2022',
        minify: true,
        emptyOutDir: true
    },

    server: {
        cors: true,
    },
})
