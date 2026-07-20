import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import flowbitePlugin from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './node_modules/flowbite/**/*.js',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // UI-03 token port (v3.0 Decision #5): Flowbite 4's semantic tokens ship in a Tailwind-v4-only
            // @theme{} block and emit no CSS under this project's Tailwind 3 build. These values are ported
            // manually into theme.extend. Colors use CSS custom-property indirection so one class name
            // resolves differently in light/dark via the existing `.dark` class flip (see resources/css/app.css).
            colors: {
                brand: {
                    DEFAULT: 'rgb(var(--color-brand) / <alpha-value>)',
                    strong: 'rgb(var(--color-brand-strong) / <alpha-value>)',
                    medium: 'rgb(var(--color-brand-medium) / <alpha-value>)',
                    soft: 'rgb(var(--color-brand-soft) / <alpha-value>)',
                },
                'fg-brand': 'rgb(var(--color-fg-brand) / <alpha-value>)',
                heading: 'rgb(var(--color-heading) / <alpha-value>)',
                body: 'rgb(var(--color-body) / <alpha-value>)',
                // Lowercase `default` is distinct from Tailwind's special uppercase `DEFAULT` key (which
                // generates the bare `.border` utility) — `.border-default` and `.border` coexist, not a collision.
                default: 'rgb(var(--color-border-default) / <alpha-value>)',
                'default-medium': 'rgb(var(--color-border-default-medium) / <alpha-value>)',
                // theme.extend deep-merges: this extends Tailwind's stock neutral-50..950 scale, it does not
                // replace it. Verified: this repo currently uses zero neutral-* utilities, so nothing regresses.
                neutral: {
                    'primary-soft': 'rgb(var(--color-neutral-primary-soft) / <alpha-value>)',
                    'secondary-medium': 'rgb(var(--color-neutral-secondary-medium) / <alpha-value>)',
                },
            },
            // No separate borderColor/ringColor block is needed: Tailwind 3's stock preset (stubs/config.full.js,
            // verified per 09-RESEARCH.md § Pattern 2) defines both as functions that spread theme('colors'), so
            // extending `colors` alone makes border-default, ring-brand, ring-brand-medium, ring-brand-soft,
            // text-fg-brand, bg-brand-strong and placeholder:text-body all resolve. Do not duplicate the colors
            // here to "fix" a missing border/ring utility — it is already covered.
            borderRadius: {
                base: '0.5rem', // → rounded-base
                // CORRECTION to 09-UI-SPEC.md / 09-RESEARCH.md (both list only `base`): the login card uses
                // rounded-xs on the remember-me checkbox (v3.md line 17). Tailwind 3's radius scale has no `xs`
                // key (none/sm/DEFAULT/md/lg/xl/2xl/3xl/full) — `xs` is a Tailwind 4 addition equal to 0.125rem
                // (what Tailwind 3 calls `sm`). Without this key rounded-xs emits nothing — UI-03's exact
                // silent-failure mode, on the very card UI-03 exists to make work.
                xs: '0.125rem',
            },
            boxShadow: {
                // Tailwind 3 calls this value shadow-sm; Flowbite 4 / Tailwind 4 call it shadow-xs.
                xs: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            },
        },
    },

    plugins: [forms, flowbitePlugin],
};
