import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'

require('dotenv').config();

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        // Main project views
        '../../resources/views/**/*.blade.php',
        '../../resources/views/**/*.php',
        
        // All app-modules views
        '../../app-modules/**/resources/views/**/*.blade.php',
        '../../app-modules/**/src/**/*.php',
        
        // Content directories in app-modules
        '../../content/**/*.yml',
        '../../app-modules/**/content/**/*.yml',
        
        // JavaScript files that might contain Tailwind classes
        '../../resources/js/**/*.js',
        '../../app-modules/**/resources/js/**/*.js',
        
        // This module's own files
        './resources/views/**/*.blade.php',
        './src/**/*.php',
        './resources/js/**/*.js',
    ],

    safelist: [
        'grid-cols-2',
        'grid-cols-3',
        'grid-cols-4',
        'grid-cols-5',
        'grid-cols-6',
        'bg-yellow-100',
        'bg-yellow-200',
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
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            display: ['group-hover'],
            colors: {
                'brand-highlight': process.env.VITE_PRIMARY_COLOR || '#000',
                'brand-bg': process.env.VITE_BG_COLOR || '#f9f9f9',
            },
        },
    },

    plugins: [forms, require('tailwind-scrollbar')],
}
