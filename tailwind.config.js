/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/views/**/*.html',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#3C50E0',
                },
                secondary: '#8FD0EF',
                dark: '#1C2434',
            },
        },
    },
    plugins: [],
};
