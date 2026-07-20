<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Subjects') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <div class="flex justify-end mb-4">
                    <a href="{{ route('lecturer.subjects.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        {{ __('New subject') }}
                    </a>
                </div>

                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Name') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Code') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($subjects as $subject)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $subject->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $subject->code }}</td>
                                <td class="px-4 py-2 text-right text-sm whitespace-nowrap space-x-4">
                                    <a href="{{ route('lecturer.subjects.manage', $subject) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('Manage') }}</a>
                                    <a href="{{ route('lecturer.subjects.edit', $subject) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('Edit') }}</a>
                                    <div x-data class="inline">
                                        <form action="{{ route('lecturer.subjects.destroy', $subject) }}" method="POST" class="inline" x-ref="deleteSubjectForm" @submit.prevent="$dispatch('open-modal', 'delete-subject-{{ $subject->id }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400">{{ __('Delete') }}</button>
                                        </form>

                                        <x-confirm-modal
                                            name="delete-subject-{{ $subject->id }}"
                                            :title="__('Delete subject?')"
                                            :body="__('This permanently removes “:name”. Subjects with exams cannot be deleted.', ['name' => $subject->name])"
                                            confirm-label="Delete"
                                            x-on:click="$refs.deleteSubjectForm.submit()"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('No subjects yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
