import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                heading: ['Montserrat', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                navy: {
                    DEFAULT: '#101B35',
                    50: '#E8EAF0',
                    100: '#C7CCDC',
                    400: '#3A4A75',
                    600: '#1B2847',
                    700: '#151F38',
                    900: '#101B35',
                },
                brand: {
                    pink: '#E44E83',
                    teal: '#16A085',
                },
                surface: {
                    light: '#F5F7FA',
                },
            },
        },
    },

    plugins: [forms],
};
