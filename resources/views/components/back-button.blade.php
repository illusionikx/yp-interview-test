@props(['href'])

{{--
    UX-04 back affordance primitive. A styled button-anchor whose visible text
    names the destination it leads to. The destination noun/phrase is NEVER
    hardcoded here — every call site supplies it via the slot (e.g.
    <x-back-button :href="route('lecturer.sections.index')">Back to classes</x-back-button>),
    so this component stays reusable across every "Cancel"/"Back" replacement
    in the app without baking in any one screen's wording.
--}}
<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700']) }}
>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
    </svg>
    {{ $slot }}
</a>
