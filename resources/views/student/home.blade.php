<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Student area') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-welcome-banner />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <x-dashboard-card
                    :label="__('Subjects enrolled this semester')"
                    :value="$subjectsThisSemester"
                />
                <x-dashboard-card
                    :label="__('Exams available to take')"
                    :value="$examsAvailable"
                />
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6" x-data="{ showPast: false }">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('Your subjects') }}</h3>
                    <a href="{{ route('student.subjects.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        {{ __('Enroll in a class') }}
                    </a>
                </div>

                @if (count($currentOrFutureGroups) === 0 && count($pastGroups) === 0)
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __("You're not enrolled in any subjects yet.") }}</p>
                @else
                    @foreach ($currentOrFutureGroups as $group)
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">{{ $group['semester']->label() }}</h4>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Subject') }}</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Lecturer') }}</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($group['subjects'] as $entry)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $entry['subject']->code }} — {{ $entry['subject']->name }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $entry['lecturerNames'] }}</td>
                                            <td class="px-4 py-2 text-right text-sm">
                                                <a href="{{ route('student.subjects.class', $entry['subject']) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('Open class page') }}</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    @if (count($pastGroups) > 0)
                        <button type="button" @click="showPast = !showPast" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 mb-4">
                            <span x-show="! showPast">{{ __('Show past semesters') }}</span>
                            <span x-show="showPast" x-cloak>{{ __('Hide past semesters') }}</span>
                        </button>

                        <div x-show="showPast" x-cloak>
                            @foreach ($pastGroups as $group)
                                <div class="mb-6">
                                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">{{ $group['semester']->label() }}</h4>
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Subject') }}</th>
                                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Lecturer') }}</th>
                                                <th class="px-4 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach ($group['subjects'] as $entry)
                                                <tr>
                                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $entry['subject']->code }} — {{ $entry['subject']->name }}</td>
                                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $entry['lecturerNames'] }}</td>
                                                    <td class="px-4 py-2 text-right text-sm">
                                                        <a href="{{ route('student.subjects.class', $entry['subject']) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('Open class page') }}</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
