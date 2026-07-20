<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('New subject') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('lecturer.subjects.store') }}">
                    @csrf

                    <div>
                        <x-input-label for="name" :value="__('Name')" class="dark:text-gray-300" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="code" :value="__('Code (optional)')" class="dark:text-gray-300" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('code')" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-6 gap-3">
                        <x-back-button :href="route('lecturer.home')">{{ __('Back to subjects') }}</x-back-button>
                        <x-primary-button>{{ __('Create subject') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
