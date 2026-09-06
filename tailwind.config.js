import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Clinical blue as primary
                primary: {
                    50: '#eef7ff',
                    100: '#d9edff',
                    200: '#bce1ff',
                    300: '#8ecfff',
                    400: '#59b4ff',
                    500: '#3395ff',
                    600: '#1c75f5',
                    700: '#145ee1',
                    800: '#174cb6',
                    900: '#19428f',
                    950: '#142957',
                },
                // Semantic status colors
                success: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                },
                warning: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                },
                danger: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                },
                // Neutral grays
                neutral: {
                    50: '#fafafa',
                    100: '#f5f5f5',
                    200: '#e5e5e5',
                    300: '#d4d4d4',
                    400: '#a3a3a3',
                    500: '#737373',
                    600: '#525252',
                    700: '#404040',
                    800: '#262626',
                    900: '#171717',
                    950: '#0a0a0a',
                },
            },
            spacing: {
                '18': '4.5rem',
                '88': '22rem',
                '112': '28rem',
                '128': '32rem',
            },
            borderRadius: {
                'sm': '0.25rem',
                DEFAULT: '0.375rem',
                'md': '0.5rem',
                'lg': '0.75rem',
                'xl': '1rem',
            },
            boxShadow: {
                'sm': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
                DEFAULT: '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
                'md': '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
                'lg': '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
            },
            // Motion for the public pages (landing + sign in). Entrance
            // animations use `backwards` fill mode so a staggered
            // [animation-delay:...] holds the "from" state instead of
            // flashing unstyled content, and so hover transforms still work
            // once the animation has finished. app.css disables all of these
            // under prefers-reduced-motion.
            keyframes: {
                'fade-up': {
                    from: { opacity: '0', transform: 'translateY(1.5rem)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
                'fade-in-scale': {
                    from: { opacity: '0', transform: 'translateY(1rem) scale(0.98)' },
                    to: { opacity: '1', transform: 'translateY(0) scale(1)' },
                },
                'slow-zoom': {
                    from: { transform: 'scale(1)' },
                    to: { transform: 'scale(1.08)' },
                },
                'float-slow': {
                    '0%, 100%': { transform: 'translate3d(0, 0, 0) scale(1)' },
                    '50%': { transform: 'translate3d(1.5rem, -2rem, 0) scale(1.12)' },
                },
                'drift-slow': {
                    '0%, 100%': { transform: 'translate3d(0, 0, 0) scale(1)' },
                    '50%': { transform: 'translate3d(-2rem, 1.5rem, 0) scale(1.08)' },
                },
                'ping-dot': {
                    '0%': { boxShadow: '0 0 0 0 rgb(89 180 255 / 0.5)' },
                    '70%': { boxShadow: '0 0 0 0.5rem rgb(89 180 255 / 0)' },
                    '100%': { boxShadow: '0 0 0 0 rgb(89 180 255 / 0)' },
                },
                'sheen': {
                    from: { transform: 'translateX(-100%)' },
                    to: { transform: 'translateX(100%)' },
                },
            },
            animation: {
                'fade-up': 'fade-up 0.75s cubic-bezier(0.16, 1, 0.3, 1) backwards',
                'fade-in': 'fade-in 0.9s ease-out backwards',
                'fade-in-scale': 'fade-in-scale 0.8s cubic-bezier(0.16, 1, 0.3, 1) backwards',
                'slow-zoom': 'slow-zoom 28s ease-in-out infinite alternate',
                'float-slow': 'float-slow 16s ease-in-out infinite',
                'drift-slow': 'drift-slow 22s ease-in-out infinite',
                'ping-dot': 'ping-dot 2.8s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                'sheen': 'sheen 7s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
