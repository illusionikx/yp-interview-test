<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Pre-paint dark-mode bootstrap (UI-02) — reads localStorage 'theme',
             falling back to the OS preference when unset, and applies the
             `dark` class before first paint so there is no flash of the
             wrong theme on reload. Must run before the Vite scripts below
             so the class is set before any stylesheet-dependent paint.
             Copied verbatim from layouts/app.blade.php / layouts/landing.blade.php
             so all three shells drive the same `theme` localStorage key. -->
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
    <body class="font-sans text-heading antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-neutral-secondary-medium">
            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                </a>
            </div>

            {{-- Sized, non-card container: the login card (auth/login.blade.php) already supplies
                 its own card treatment (bg-neutral-primary-soft, border-default, rounded-base,
                 shadow-xs) per NAV-02's verbatim reproduction, so this wrapper must not also be a
                 card or login gets a double-card. Sibling guest pages (register, forgot-password,
                 reset-password, verify-email, confirm-password) that relied on this wrapper's old
                 bg-white/shadow-md/rounded-lg card now carry that treatment on their own root
                 element instead — see each view. --}}
            <div class="w-full sm:max-w-md mt-6">
                {{ $slot }}
            </div>
        </div>

        <x-toast />
    </body>
</html>
