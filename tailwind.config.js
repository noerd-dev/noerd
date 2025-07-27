import { defineConfig } from 'tailwindcss/config';
import forms from '@tailwindcss/forms';

export default defineConfig({
    content: [
        './resources/views/**/*.blade.php',
        './src/**/*.php',
    ],

    safelist: [
        'grid-cols-2',
        'grid-cols-3',
        'grid-cols-4',
        'grid-cols-5',
        'grid-cols-6',
        'grid-cols-7',
        'grid-cols-8',
        'grid-cols-9',
        'grid-cols-10',
        'grid-cols-11',
        'grid-cols-12',
        'bg-[#0298b0]',
        'col-span-1',
        'col-span-2',
        'col-span-3',
        'col-span-4',
        'col-span-5',
        'col-span-6',
        'col-span-7',
        'col-span-8',
        'col-span-9',
        'col-span-10',
        'col-span-11',
        'col-span-12',
        'min-h-36',
        'lg:ml-72',
        'bg-green-60'
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
