<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                {{ __('Edit Class') }} — {{ $subject->name }} ({{ $section->name }})
            </h2>
            <x-back-button :href="route('lecturer.sections.show', $section)">{{ __('Back to classes') }}</x-back-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @include('lecturer.sections._settings', ['subject' => $subject, 'section' => $section])
        </div>
    </div>
</x-app-layout>
