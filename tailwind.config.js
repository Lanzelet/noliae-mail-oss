/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                noliae: {
                    DEFAULT: '#FF4D2E',
                    pulse:   '#FF4D2E',
                    dark:    '#df3c1f',
                    ink:     '#15151a',
                },
            },
            fontFamily: {
                mono: ['ui-monospace', 'Menlo', 'Consolas', 'monospace'],
            },
        },
    },
    plugins: [],
};
