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
            colors: {
                'masa-madre': '#6B5B45',
                'corteza':    '#3D2B1F',
                'harina':     '#FAF7F2',
                'miga':       '#F2EAD8',
                'horno':      '#C8622A',
                'membrillo':  '#E8A820',
                'brown': {
                    50:  '#FAF6F1',
                    100: '#EFE3D5',
                    200: '#DEC4A8',
                    300: '#C49272',
                    400: '#B8794F',
                    500: '#9B6240',
                    700: '#6B3D1E',
                    900: '#1A110A',
                },
            },
            fontFamily: {
                sans:  ['Inter', ...defaultTheme.fontFamily.sans],
                serif: ['Lora', ...defaultTheme.fontFamily.serif],
                mono:  ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
        },
    },

    plugins: [forms],
};
