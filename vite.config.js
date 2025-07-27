import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'dist',
        rollupOptions: {
            input: 'resources/js/main.js',
            output: {
                assetFileNames: 'noerd.css'
            }
        },
        target: 'es2022',
        minify: true,
        // Prevent generation of a JS file
        write: true,
        emptyOutDir: true
    },
    css: {
        postcss: './postcss.config.js',
    }
}); 