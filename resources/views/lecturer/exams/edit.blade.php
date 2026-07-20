<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Edit exam') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div x-data class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <form
                    method="POST"
                    action="{{ route('lecturer.exams.update', $exam) }}"
                    @if ($attemptCounts['total'] > 0)
                        x-ref="editExamForm"
                        @submit.prevent="$dispatch('open-modal', 'save-exam-changes')"
                    @endif
                >
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="subject_id" :value="__('Subject')" class="dark:text-gray-300" />
                        <select id="subject_id" name="subject_id" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500 rounded-md shadow-sm" required>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id', $exam->subject_id) == $subject->id)>{{ $subject->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="title" :value="__('Title')" class="dark:text-gray-300" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('title', $exam->title)" required autofocus />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="description" :value="__('Description (optional)')" class="dark:text-gray-300" />
                        <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500 rounded-md shadow-sm">{{ old('description', $exam->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="duration_minutes" :value="__('Duration (minutes)')" class="dark:text-gray-300" />
                        <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="1" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('duration_minutes', $exam->duration_minutes)" required />
                        <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <x-input-label for="available_from" :value="__('Available from (optional)')" class="dark:text-gray-300" />
                            <x-text-input id="available_from" name="available_from" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('available_from', $exam->available_from?->format('Y-m-d\TH:i'))" />
                            <x-input-error :messages="$errors->get('available_from')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="available_until" :value="__('Available until (optional)')" class="dark:text-gray-300" />
                            <x-text-input id="available_until" name="available_until" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('available_until', $exam->available_until?->format('Y-m-d\TH:i'))" />
                            <x-input-error :messages="$errors->get('available_until')" class="mt-2" />
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Leave blank for no restriction on that side.') }}</p>

                    <div class="flex items-center justify-end mt-6 gap-3">
                        <x-back-button :href="route('lecturer.exams.show', $exam)">{{ __('Back to exam') }}</x-back-button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            {{ __('Save changes') }}
                        </button>
                    </div>
                </form>

                @include('lecturer.exams._save-warning-modal', ['exam' => $exam, 'attemptCounts' => $attemptCounts, 'formRef' => 'editExamForm'])
            </div>
        </div>
    </div>
</x-app-layout>
