import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Los colores viven como variables CSS en resources/css/app.css (canales RGB)
 * para que cada tema sólo redefina las variables.
 *
 * @param {string} name
 */
const token = (name) => `rgb(var(--c-${name}) / <alpha-value>)`;

/**
 * Escala de Tailwind tematizable, limitada a los tonos que usa la app: un tono que
 * no está acá no genera clase, así que no puede colarse un color sin variable.
 *
 * @param {string} name
 * @param {number[]} shades
 */
const scale = (name, shades) => Object.fromEntries(shades.map((shade) => [shade, token(`${name}-${shade}`)]));

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        colors: {
            transparent: 'transparent',
            current: 'currentColor',
            inherit: 'inherit',
            white: '#fff',
            black: '#000',

            'harina':      token('harina'),
            'superficie':  token('superficie'),
            'miga':        token('miga'),
            'corteza':     token('corteza'),
            'masa-madre':  token('masa-madre'),
            'horno':       token('horno'),
            'membrillo':   token('membrillo'),
            'tinta':       token('tinta'),
            'barra':       token('barra'),
            'sobre-barra': token('sobre-barra'),

            brown:  scale('brown', [50, 100, 200, 300, 400, 500, 700, 900]),
            gray:   scale('gray', [50, 100, 200, 300, 400, 500, 600, 700, 800, 900]),
            red:    scale('red', [50, 100, 200, 300, 400, 500, 600, 700, 800]),
            green:  scale('green', [50, 100, 200, 300, 500, 600, 700, 800]),
            amber:  scale('amber', [50, 100, 200, 300, 400, 500, 600, 700, 800, 900]),
            yellow: scale('yellow', [50, 300, 600, 700, 800, 900]),
            orange: scale('orange', [100, 200, 400, 600, 700]),
            blue:   scale('blue', [50, 100, 200, 300, 700, 800]),
            indigo: scale('indigo', [50, 100, 400, 500, 700, 800]),
            violet: scale('violet', [100, 600, 700]),
            sky:    scale('sky', [50, 100, 700]),
        },
        extend: {
            fontFamily: {
                sans:  ['Inter', ...defaultTheme.fontFamily.sans],
                serif: ['Lora', ...defaultTheme.fontFamily.serif],
                mono:  ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
        },
    },

    plugins: [forms],
};
