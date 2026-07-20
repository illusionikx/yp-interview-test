{{--
    A third, dedicated shell for the guest landing page (NAV-01, UX-01).

    Why not reuse an existing layout: `layouts/guest.blade.php` hard-codes a
    centered narrow card (`sm:max-w-md`, `flex flex-col sm:justify-center
    items-center`) with no top-bar slot, and the login page still needs that
    exact behavior unchanged — so it cannot be branched to also serve a
    full-bleed hero. It also lacks the pre-paint dark-mode bootstrap, which
    the landing page needs because D-09 puts the dark-mode toggle in its top
    bar. `layouts/app.blade.php` has the bootstrap but pulls in the full
    authenticated navbar via an include, which must not render for a guest.
    Neither fits; this shell takes the `<head>` from the former and the
    bootstrap from the latter, with a full-bleed `<body>` and its own slim
    top bar.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Pre-paint dark-mode bootstrap (UI-02) — reads localStorage 'theme',
             falling back to the OS preference when unset, and applies the
             `dark` class before first paint so there is no flash of the
             wrong theme on reload. Must run before the Vite scripts below
             so the class is set before any stylesheet-dependent paint. -->
        <script>
            if (localStorage.getItem('theme') === 'dark' ||
                (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex flex-col bg-neutral-primary-soft">
            <!-- Slim top bar (D-09): wordmark left, dark-mode toggle + Sign in right.
                 Unauthenticated — no navbar include, no role-scoped links, no user
                 dropdown. -->
            <header class="flex items-center justify-between px-4 py-4">
                <span class="text-xl font-semibold text-heading">
                    {{ __('Online Examination Portal') }}
                </span>

                <div class="flex items-center gap-2">
                    <!-- Dark-mode toggle (UI-02) — copied verbatim from the authenticated
                         navbar so both toggles drive the same `theme` localStorage key
                         and the same `dark` class. -->
                    <button
                        type="button"
                        x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        x-on:click="
                            dark = ! dark;
                            document.documentElement.classList.toggle('dark', dark);
                            localStorage.setItem('theme', dark ? 'dark' : 'light');
                        "
                        aria-label="{{ __('Toggle dark mode') }}"
                        class="inline-flex items-center justify-center rounded-lg p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    >
                        <svg x-show="! dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                        <svg x-show="dark" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>

                    {{-- Top-bar "Sign in" stays a plain link, not a filled button — the hero's
                         "Sign in" CTA below is the phase's one accent-filled primary button
                         (09-UI-SPEC.md's Color contract: "do not add a second filled button"). --}}
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-base px-3 py-2 text-sm font-semibold text-fg-brand hover:underline focus:outline-none focus:ring-2 focus:ring-brand-medium"
                    >
                        {{ __('Sign in') }}
                    </a>
                </div>
            </header>

            <main class="flex-1">
                {{ $slot }}
            </main>
        </div>

        <x-toast />
    </body>
</html>
