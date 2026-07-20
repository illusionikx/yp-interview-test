<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Manage Sections') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @php
                $grouped = $sections->groupBy(fn ($section) => $section->subject->name);
            @endphp

            @forelse ($grouped as $subjectName => $subjectSections)
                @php
                    $subject = $subjectSections->first()->subject;
                @endphp
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        {{-- The subject heading links to its own page, which is where classes
                             are created and managed (the "Create class" button lives on that
                             page's Classes tab, not here). --}}
                        <a href="{{ route('lecturer.subjects.manage', $subject) }}" class="text-xl font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 hover:underline">{{ $subjectName }}</a>
                        <a href="{{ route('lecturer.subjects.manage', $subject) }}?tab=classes"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            {{ __('Manage classes') }}
                        </a>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Section') }}</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Capacity') }}</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($subjectSections as $section)
                                @php
                                    $windowStatus = $section->windowStatus();
                                    $windowLabel = match ($windowStatus) {
                                        'opens' => __('Opens :date', ['date' => $section->opens_at->format('M j, Y')]),
                                        'closed' => __('Closed'),
                                        default => __('Open'),
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $section->name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $section->capacity }}</td>
                                    <td class="px-4 py-2 text-sm"><x-status-pill :status="$windowStatus">{{ $windowLabel }}</x-status-pill></td>
                                    <td class="px-4 py-2 text-right text-sm whitespace-nowrap space-x-4">
                                        <a href="{{ route('lecturer.sections.show', $section) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('View') }}</a>
                                        <button type="button" x-data @click="$dispatch('open-modal', 'delete-section-{{ $section->id }}')" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400">{{ __('Delete') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @foreach ($subjectSections as $section)
                    <x-modal name="delete-section-{{ $section->id }}" focusable>
                        <div class="p-6">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Delete Section') }}</h2>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('This permanently removes the section and cannot be undone. Any existing enrollments will be lost.') }}</p>
                            <div class="mt-6 flex justify-end gap-3">
                                <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                                <form method="POST" action="{{ route('lecturer.subjects.sections.destroy', [$subject, $section]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button>{{ __('Delete Section') }}</x-danger-button>
                                </form>
                            </div>
                        </div>
                    </x-modal>
                @endforeach
            @empty
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 text-center">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('No sections yet') }}</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Create a section to open enrollment for this subject.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
