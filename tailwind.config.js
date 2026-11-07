import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Los colores viven como variables CSS en resources/css/app.css (canales RGB)
 * para que cada tema sólo redefina las variables.
 *
 * @param {string} name
 */
const token = (name) => `rgb(var(--c-${name}) / <alpha-value>)`;

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
                'masa-madre': token('masa-madre'),
                'corteza':    token('corteza'),
                'harina':     token('harina'),
                'miga':       token('miga'),
                'horno':      token('horno'),
                'membrillo':  token('membrillo'),
                'brown': {
                    50:  token('brown-50'),
                    100: token('brown-100'),
                    200: token('brown-200'),
                    300: token('brown-300'),
                    400: token('brown-400'),
                    500: token('brown-500'),
                    700: token('brown-700'),
                    900: token('brown-900'),
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
