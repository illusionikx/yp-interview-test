<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Lecturer area') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-welcome-banner />

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <x-dashboard-card
                    :label="__('Classes this & future semesters')"
                    :value="$classesThisAndFuture"
                />
                <x-dashboard-card
                    :label="__('Students enrolled / seats')"
                    :value="$enrolledStudents . ' / ' . $totalSeats"
                    :progress-current="$enrolledStudents"
                    :progress-max="$totalSeats"
                />
                <x-dashboard-card
                    :label="__('Attempts awaiting grading')"
                    :value="$awaitingGrading"
                />
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('Your subjects') }}</h3>
                    <a href="{{ route('lecturer.subjects.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        {{ __('New subject') }}
                    </a>
                </div>

                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Code') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Name') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('#Classes') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('#Exams') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($subjects as $subject)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $subject->code }}</td>
                                <td class="px-4 py-2 text-sm">
                                    {{-- The subject name opens the subject page, which is now where
                                         it's managed (classes/exams) and deleted. --}}
                                    <a href="{{ route('lecturer.subjects.manage', $subject) }}" class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 hover:underline">{{ $subject->name }}</a>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $subject->sections_count }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $subject->exams_count }}</td>
                                <td class="px-4 py-2 text-right text-sm whitespace-nowrap">
                                    <a href="{{ route('lecturer.subjects.edit', $subject) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('Edit') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('No subjects assigned to you yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
