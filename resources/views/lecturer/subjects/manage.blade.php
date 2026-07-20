<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                {{ $subject->code }} — {{ $subject->name }}
            </h2>
            <div class="flex items-center gap-3">
                {{-- Delete now lives on the subject's own page (moved off the home list). --}}
                <div x-data class="inline">
                    <form action="{{ route('lecturer.subjects.destroy', $subject) }}" method="POST" x-ref="deleteSubjectForm" @submit.prevent="$dispatch('open-modal', 'delete-subject')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 dark:border-red-800 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-semibold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 focus:outline-none focus:ring-4 focus:ring-red-100 dark:focus:ring-red-900">
                            {{ __('Delete subject') }}
                        </button>
                    </form>

                    <x-confirm-modal
                        name="delete-subject"
                        :title="__('Delete subject?')"
                        :body="__('This permanently removes “:name”. A subject that still has exams or classes cannot be deleted — remove those first.', ['name' => $subject->name])"
                        confirm-label="Delete"
                        x-on:click="$refs.deleteSubjectForm.submit()"
                    />
                </div>
                <x-back-button :href="route('lecturer.home')">{{ __('Back to subjects') }}</x-back-button>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ tab: '{{ request('tab', 'classes') }}' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-6">
                    <button
                        type="button"
                        @click="tab = 'classes'"
                        :class="tab === 'classes' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold"
                    >
                        {{ __('Classes') }}
                    </button>
                    <button
                        type="button"
                        @click="tab = 'exams'"
                        :class="tab === 'exams' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold"
                    >
                        {{ __('Exams') }}
                    </button>
                </nav>
            </div>

            <div x-show="tab === 'classes'">
                @include('lecturer.subjects.partials._classes-tab')
            </div>

            <div x-show="tab === 'exams'" x-cloak>
                @include('lecturer.subjects.partials._exams-tab')
            </div>
        </div>
    </div>
</x-app-layout>
