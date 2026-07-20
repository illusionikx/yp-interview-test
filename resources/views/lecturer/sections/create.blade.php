<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Create Section') }} — {{ $subject->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                @if ($errors->any())
                    <div class="mb-4 text-sm text-red-600 dark:text-red-400">
                        {{ __("Couldn't save this section. Check the capacity and enrollment dates, then try again.") }}
                    </div>
                @endif

                <form method="POST" action="{{ route('lecturer.subjects.sections.store', $subject) }}">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="year" :value="__('Year')" class="dark:text-gray-300" />
                            <x-text-input id="year" name="year" type="number" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('year', now()->year)" required autofocus />
                            <x-input-error :messages="$errors->get('year')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="semester" :value="__('Semester')" class="dark:text-gray-300" />
                            <select id="semester" name="semester" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500 rounded-md shadow-sm" required>
                                <option value="1" @selected(old('semester') == 1)>1</option>
                                <option value="2" @selected(old('semester', 2) == 2)>2</option>
                            </select>
                            <x-input-error :messages="$errors->get('semester')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="capacity" :value="__('Capacity')" class="dark:text-gray-300" />
                        <x-text-input id="capacity" name="capacity" type="number" min="1" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('capacity')" required />
                        <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="location" :value="__('Location (optional)')" class="dark:text-gray-300" />
                        <x-text-input id="location" name="location" type="text" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('location')" />
                        <x-input-error :messages="$errors->get('location')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <x-input-label for="opens_at" :value="__('Opens at')" class="dark:text-gray-300" />
                            <x-text-input id="opens_at" name="opens_at" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('opens_at')" required />
                            <x-input-error :messages="$errors->get('opens_at')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="closes_at" :value="__('Closes at')" class="dark:text-gray-300" />
                            <x-text-input id="closes_at" name="closes_at" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('closes_at')" required />
                            <x-input-error :messages="$errors->get('closes_at')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6 gap-3">
                        <x-back-button :href="route('lecturer.subjects.manage', $subject)">{{ __('Back to classes') }}</x-back-button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            {{ __('Create Section') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
