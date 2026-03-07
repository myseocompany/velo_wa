import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#ffe8f3',
                    100: '#ffd1e7',
                    200: '#ff9ecb',
                    300: '#ff6bad',
                    400: '#ff4a97',
                    500: '#f5257e',
                    600: '#d81b6b',
                    700: '#b31558',
                    800: '#8d1247',
                    900: '#641136',
                },
                ink: {
                    50: '#ececfa',
                    100: '#d7d7f4',
                    200: '#b0b0e8',
                    300: '#8a8adc',
                    400: '#6363d0',
                    500: '#3d3dc4',
                    600: '#2e2e97',
                    700: '#1f1f6b',
                    800: '#121240',
                    900: '#060318',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
