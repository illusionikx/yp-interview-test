@props(['status'])

@php
    // Semantic status-pill system (UI-01) — maps an arbitrary caller-supplied
    // status string to exactly one of four locked palettes via a fixed
    // match() allowlist (T-07-01: never interpolate the raw status into the
    // class attribute). Unknown/unrecognised input falls back to gray.
    $normalized = strtolower(trim((string) $status));

    $classes = match ($normalized) {
        'enrolled', 'published', 'open', 'available' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'rejected', 'closed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'full' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
        'withdrawn', 'opening', 'opens' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "rounded-full px-2.5 py-0.5 text-xs font-semibold {$classes}"]) }}>
    {{ $slot }}
</span>
