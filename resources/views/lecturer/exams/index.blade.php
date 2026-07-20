<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Exams') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <div class="flex justify-end mb-4">
                    <a href="{{ route('lecturer.exams.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        {{ __('New exam') }}
                    </a>
                </div>

                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Title') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Subject') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Duration') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($exams as $exam)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    <a href="{{ route('lecturer.exams.show', $exam) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ $exam->title }}</a>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $exam->subject->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $exam->duration_minutes }} {{ __('min') }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <x-status-pill :status="$exam->is_published ? 'published' : 'draft'">
                                        {{ $exam->is_published ? __('Published') : __('Draft') }}
                                    </x-status-pill>
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <a href="{{ route('lecturer.exams.show', $exam) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('View') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('No exams yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
