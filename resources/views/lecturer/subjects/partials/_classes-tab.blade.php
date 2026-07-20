{{--
    CLS-02/CLS-03: classes grouped by semester, each row carrying a
    total/max-students progress bar and view/edit/delete actions. Reuses
    student/home.blade.php's showPast Alpine collapse pattern for past
    semesters (including its "duplicate the table markup for the past
    group" precedent, rather than introducing a new partial file outside
    this plan's declared file list). "Class"/"class" in all user-facing
    copy (Decision #3 relabel) while routes/models stay Section.
--}}
<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6" x-data="{ showPast: false }">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('Classes') }}</h3>
        <a href="{{ route('lecturer.subjects.sections.create', $subject) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
            {{ __('Create class') }}
        </a>
    </div>

    @if (count($currentOrFutureGroups) === 0 && count($pastGroups) === 0)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No classes yet for this subject.') }}</p>
    @else
        @foreach ($currentOrFutureGroups as $group)
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">{{ $group['semester']->label() }}</h4>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Class code') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Students') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($group['sections'] as $section)
                            @php
                                $progressPercent = $section->capacity > 0
                                    ? max(0, min(100, (int) round(($section->enrolled_total / $section->capacity) * 100)))
                                    : 0;
                            @endphp
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $section->name }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <div class="h-2 w-32 rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div class="h-2 rounded-full bg-blue-600" style="width: {{ $progressPercent }}%"></div>
                                    </div>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $section->enrolled_total }} / {{ $section->capacity }}</span>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    <x-status-pill :status="$section->windowStatus()">{{ ucfirst($section->windowStatus()) }}</x-status-pill>
                                </td>
                                <td class="px-4 py-2 text-right text-sm whitespace-nowrap space-x-4">
                                    <a href="{{ route('lecturer.sections.show', $section) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('View') }}</a>
                                    <div x-data class="inline">
                                        <form action="{{ route('lecturer.subjects.sections.destroy', [$subject, $section]) }}" method="POST" class="inline" x-ref="deleteClassForm{{ $section->id }}" @submit.prevent="$dispatch('open-modal', 'delete-class-{{ $section->id }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400">{{ __('Delete') }}</button>
                                        </form>

                                        <x-confirm-modal
                                            name="delete-class-{{ $section->id }}"
                                            :title="__('Delete class?')"
                                            :body="__('This permanently removes class “:name”. Any existing enrollments will be lost.', ['name' => $section->name])"
                                            confirm-label="Delete"
                                            x-on:click="$refs['deleteClassForm{{ $section->id }}'].submit()"
                                        />
                                    </div>
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
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Class code') }}</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Students') }}</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($group['sections'] as $section)
                                    @php
                                        $progressPercent = $section->capacity > 0
                                            ? max(0, min(100, (int) round(($section->enrolled_total / $section->capacity) * 100)))
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $section->name }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <div class="h-2 w-32 rounded-full bg-gray-200 dark:bg-gray-700">
                                                <div class="h-2 rounded-full bg-blue-600" style="width: {{ $progressPercent }}%"></div>
                                            </div>
                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $section->enrolled_total }} / {{ $section->capacity }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            <x-status-pill :status="$section->windowStatus()">{{ ucfirst($section->windowStatus()) }}</x-status-pill>
                                        </td>
                                        <td class="px-4 py-2 text-right text-sm whitespace-nowrap space-x-4">
                                            <a href="{{ route('lecturer.sections.show', $section) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('View') }}</a>
                                            <div x-data class="inline">
                                                <form action="{{ route('lecturer.subjects.sections.destroy', [$subject, $section]) }}" method="POST" class="inline" x-ref="deleteClassForm{{ $section->id }}" @submit.prevent="$dispatch('open-modal', 'delete-class-{{ $section->id }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400">{{ __('Delete') }}</button>
                                                </form>

                                                <x-confirm-modal
                                                    name="delete-class-{{ $section->id }}"
                                                    :title="__('Delete class?')"
                                                    :body="__('This permanently removes class “:name”. Any existing enrollments will be lost.', ['name' => $section->name])"
                                                    confirm-label="Delete"
                                                    x-on:click="$refs['deleteClassForm{{ $section->id }}'].submit()"
                                                />
                                            </div>
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
