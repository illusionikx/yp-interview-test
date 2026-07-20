@props([
    'name',
    'title',
    'body',
    'confirmLabel' => 'Delete',
    'cancelLabel' => 'Cancel',
    'danger' => true,
])

@php
    // UX-02's one blocking-confirmation style. Reuses the modal primitive's open-modal/close-modal
    // window-event contract verbatim (matched by $name) rather than inventing a second one.
    // Never bake the three call-site messages in here — Phase 10's INT-02/CLS-07 warnings
    // consume this same component with their own dynamic title/body.
    $confirmClasses = match (true) {
        $danger => 'bg-red-600 hover:bg-red-700 text-white dark:bg-red-600 dark:hover:bg-red-700',
        default => 'bg-brand hover:bg-brand-strong text-white',
    };
@endphp

<x-modal :name="$name" max-width="md" focusable>
    <div class="p-6">
        <h2 class="text-lg font-semibold text-heading">{{ $title }}</h2>
        <p class="mt-2 text-sm text-body">{{ $body }}</p>

        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                x-on:click="$dispatch('close-modal', '{{ $name }}')"
                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-300 dark:focus:ring-blue-800"
            >
                {{ $cancelLabel }}
            </button>

            {{-- The caller stays in control of *what* confirming does (usually
                 $refs.someForm.submit()) by passing it through as a plain attribute
                 (e.g. x-on:click="..."), which lands here via $attributes since it is
                 not one of the declared @props above. This keeps the component agnostic
                 about the action it confirms. --}}
            <button
                type="button"
                {{ $attributes->merge(['class' => "inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:focus:ring-blue-800 {$confirmClasses}"]) }}
            >
                {{ $confirmLabel }}
            </button>
        </div>
    </div>
</x-modal>
