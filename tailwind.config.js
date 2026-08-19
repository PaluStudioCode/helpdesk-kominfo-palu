import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                kominfo: {
                    primary: '#0284c7', // Sky-600
                    'primary-dark': '#0369a1', // Sky-700
                    accent: '#38bdf8', // Sky-400
                },
                border: 'hsl(var(--border, 214.3 31.8% 91.4%))',
                input: 'hsl(var(--input, 214.3 31.8% 91.4%))',
                ring: 'hsl(var(--ring, 198.6 88.7% 48.4%))',
                background: 'hsl(var(--background, 0 0% 100%))',
                foreground: 'hsl(var(--foreground, 222.2 84% 4.9%))',
                primary: {
                    DEFAULT: 'hsl(var(--primary, 198.6 88.7% 48.4%))',
                    foreground: 'hsl(var(--primary-foreground, 210 40% 98%))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary, 210 40% 96.1%))',
                    foreground: 'hsl(var(--secondary-foreground, 222.2 47.4% 11.2%))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive, 0 84.2% 60.2%))',
                    foreground: 'hsl(var(--destructive-foreground, 210 40% 98%))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted, 210 40% 96.1%))',
                    foreground: 'hsl(var(--muted-foreground, 215.4 16.3% 46.9%))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent, 210 40% 96.1%))',
                    foreground: 'hsl(var(--accent-foreground, 222.2 47.4% 11.2%))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover, 0 0% 100%))',
                    foreground: 'hsl(var(--popover-foreground, 222.2 84% 4.9%))',
                },
                card: {
                    DEFAULT: 'hsl(var(--card, 0 0% 100%))',
                    foreground: 'hsl(var(--card-foreground, 222.2 84% 4.9%))',
                },
            },
        },
    },

    plugins: [forms],
};

