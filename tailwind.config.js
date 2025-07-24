import { defineConfig } from 'tailwindcss/config';
import forms from '@tailwindcss/forms';

export default defineConfig({
    content: [
        './resources/views/**/*.blade.php',
        './src/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                'brand': {
                    'bg': '#f8fafc',
                    'highlight': '#000000',
                },
                'gray': {
                    50: '#f9fafb',
                    100: '#f3f4f6',
                    200: '#e5e7eb',
                    300: '#d1d5db',
                    400: '#9ca3af',
                    500: '#6b7280',
                    600: '#4b5563',
                    700: '#374151',
                    800: '#1f2937',
                    900: '#111827',
                },
                'zinc': {
                    200: '#e4e4e7',
                    300: '#d4d4d8',
                    400: '#a1a1aa',
                    500: '#71717a',
                    700: '#3f3f46',
                },
            },
            fontFamily: {
                'sans': ['Nunito Sans', 'sans-serif'],
            },
            spacing: {
                'xs': '1px',
            },
            borderRadius: {
                'xs': '0.25rem',
            },
            boxShadow: {
                'xs': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            },
        },
    },
    plugins: [
        forms({
            strategy: 'class',
        }),
    ],
}); 