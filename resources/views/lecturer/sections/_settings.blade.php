{{--
    Shared class (section) settings: the edit form + the delete action. Included
    by both the standalone edit page and the class page's Settings tab. Requires
    $subject and $section in scope. No back button here — each host page provides
    its own in the header.
--}}
@if ($errors->any())
    <div class="text-sm text-red-600 dark:text-red-400 mb-4">
        {{ __("Couldn't save this class. Check the capacity and enrollment dates, then try again.") }}
    </div>
@endif

<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
    <form method="POST" action="{{ route('lecturer.subjects.sections.update', [$subject, $section]) }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="year" :value="__('Year')" class="dark:text-gray-300" />
                <x-text-input id="year" name="year" type="number" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('year', $section->year)" required />
                <x-input-error :messages="$errors->get('year')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="semester" :value="__('Semester')" class="dark:text-gray-300" />
                <select id="semester" name="semester" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500 rounded-md shadow-sm" required>
                    <option value="1" @selected(old('semester', $section->semester) == 1)>1</option>
                    <option value="2" @selected(old('semester', $section->semester) == 2)>2</option>
                </select>
                <x-input-error :messages="$errors->get('semester')" class="mt-2" />
            </div>
        </div>

        <div class="mt-4">
            <x-input-label for="capacity" :value="__('Capacity')" class="dark:text-gray-300" />
            <x-text-input id="capacity" name="capacity" type="number" min="1" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('capacity', $section->capacity)" required />
            <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="location" :value="__('Location (optional)')" class="dark:text-gray-300" />
            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('location', $section->location ?? '')" />
            <x-input-error :messages="$errors->get('location')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4">
            <div>
                <x-input-label for="opens_at" :value="__('Opens at')" class="dark:text-gray-300" />
                <x-text-input id="opens_at" name="opens_at" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('opens_at', $section->opens_at->format('Y-m-d\TH:i'))" required />
                <x-input-error :messages="$errors->get('opens_at')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="closes_at" :value="__('Closes at')" class="dark:text-gray-300" />
                <x-text-input id="closes_at" name="closes_at" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('closes_at', $section->closes_at->format('Y-m-d\TH:i'))" required />
                <x-input-error :messages="$errors->get('closes_at')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center justify-end mt-6">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 mt-6 flex items-center justify-between">
    <div>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Delete this class') }}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('This permanently removes the class and cannot be undone. Any existing enrollments will be lost.') }}</p>
    </div>
    <button type="button" x-data @click="$dispatch('open-modal', 'delete-section-{{ $section->id }}')"
            class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800">
        {{ __('Delete Class') }}
    </button>
</div>

<x-modal name="delete-section-{{ $section->id }}" focusable>
    <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Delete Class') }}</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('This permanently removes the class and cannot be undone. Any existing enrollments will be lost.') }}</p>
        <div class="mt-6 flex justify-end gap-3">
            <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
            <form method="POST" action="{{ route('lecturer.subjects.sections.destroy', [$subject, $section]) }}">
                @csrf
                @method('DELETE')
                <x-danger-button>{{ __('Delete Class') }}</x-danger-button>
            </form>
        </div>
    </div>
</x-modal>
