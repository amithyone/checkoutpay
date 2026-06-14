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
                    DEFAULT: '#001bca',
                    light: '#3045e6',
                    container: '#2d3fe0',
                },
                brand: {
                    primary: '#001bca',
                    secondary: '#3045e6',
                    electric: '#4D61FF',
                },
                surface: {
                    DEFAULT: '#f8f9fe',
                    container: '#eceef3',
                    'container-low': '#f2f3f8',
                    'container-high': '#e7e8ed',
                },
                midnight: {
                    deep: '#111827',
                },
                wa: {
                    DEFAULT: '#25D366',
                    dark: '#128C7E',
                },
                success: {
                    green: '#22C55E',
                },
                secondary: '#8FD0EF',
                dark: '#1C2434',
            },
            fontFamily: {
                sans: ['"Hanken Grotesk"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
            },
            boxShadow: {
                brand: '0 10px 40px -10px rgba(0, 27, 202, 0.25)',
                premium: '0px 10px 30px rgba(45, 63, 224, 0.05)',
            },
            maxWidth: {
                container: '1280px',
            },
        },
    },
    plugins: [],
};
