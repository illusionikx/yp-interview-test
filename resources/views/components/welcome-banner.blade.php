@props([
    'name' => null,
])

@php
    // DASH-02: purely decorative greeting banner, no data dependency beyond
    // the authenticated user's name (which every dashboard page already has).
    $displayName = $name ?? auth()->user()?->name;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-800 dark:to-blue-900 px-6 py-8 shadow-sm']) }}>
    <h2 class="text-2xl font-semibold text-white">
        {{ __('Welcome back, :name', ['name' => $displayName]) }}
    </h2>
</div>
