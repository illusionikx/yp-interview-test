@props([
    'label',
    'value',
    'progressCurrent' => null,
    'progressMax' => null,
])

@php
    // Presentational only — no queries in here (per plan). Callers (11-02/
    // 11-03 dashboards) pass already-aggregated COUNT/SUM values.
    $hasProgress = ! is_null($progressCurrent) && ! is_null($progressMax);

    $progressPercent = 0;
    if ($hasProgress && $progressMax > 0) {
        $progressPercent = max(0, min(100, (int) round(($progressCurrent / $progressMax) * 100)));
    }
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm']) }}>
    <span class="block text-sm font-semibold text-body">{{ $label }}</span>
    <span class="mt-1 block text-3xl font-semibold text-heading">{{ $value }}</span>

    @if ($hasProgress)
        <div class="mt-3">
            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-brand" style="width: {{ $progressPercent }}%"></div>
            </div>
            <span class="mt-1 block text-xs text-body">{{ $progressCurrent }} / {{ $progressMax }}</span>
        </div>
    @endif
</div>
