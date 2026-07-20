{{--
    CLS-04/CLS-08 — the subject's real exams management surface: create,
    edit (into the two-tab editor), the shipped CLS-06 draft/active toggle
    and CLS-07 reset-submissions surfaced inline per row, and a bounded
    grading-progress aggregate per exam. $exams carries `attempts_count` /
    `graded_attempts_count` (withCount, SubjectManageController::show()) and
    $attemptCountsByExam carries the shipped AttemptVoider::summarize() per
    exam id for the reset confirm-modal's exact counts (INT-02) — nothing
    here re-derives either.
--}}
<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('Exams') }}</h3>
        <a href="{{ route('lecturer.exams.create', ['subject' => $subject]) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
            {{ __('New exam') }}
        </a>
    </div>

    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead>
            <tr>
                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Title') }}</th>
                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Grading') }}</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse ($exams as $exam)
                @php
                    $counts = $attemptCountsByExam[$exam->id];
                    $gradedPercent = $exam->attempts_count > 0
                        ? (int) round(($exam->graded_attempts_count / $exam->attempts_count) * 100)
                        : 0;
                    $resetBody = $counts['graded'] === 0
                        ? __(':notYetGraded student(s) have started this exam but have not been graded. Resetting will permanently delete :total attempt(s) so they can start again. This cannot be undone.', ['notYetGraded' => $counts['notYetGraded'], 'total' => $counts['total']])
                        : __(':notYetGraded student(s) have started this exam but have not been graded, and :graded student(s) have already been graded. Resetting will permanently delete all :total attempts — including the :graded graded score(s). This cannot be undone.', ['notYetGraded' => $counts['notYetGraded'], 'graded' => $counts['graded'], 'total' => $counts['total']]);
                @endphp
                <tr>
                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                        <a href="{{ route('lecturer.exams.show', $exam) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ $exam->title }}</a>
                    </td>
                    <td class="px-4 py-2 text-sm">
                        <div class="flex items-center gap-2">
                            <x-status-pill :status="$exam->is_published ? 'published' : 'draft'">
                                {{ $exam->is_published ? __('Published') : __('Draft') }}
                            </x-status-pill>

                            @if ($exam->is_published)
                                <form method="POST" action="{{ route('lecturer.exams.unpublish', $exam) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center px-2 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-xs font-semibold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-300 dark:focus:ring-blue-800">
                                        {{ __('Unpublish') }}
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('lecturer.exams.publish', $exam) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        {{ __('Publish') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                        @if ($exam->attempts_count === 0)
                            <span class="text-gray-500 dark:text-gray-400">{{ __('No attempts yet') }}</span>
                        @else
                            <div class="flex items-center gap-2">
                                <span>{{ __(':graded / :total graded', ['graded' => $exam->graded_attempts_count, 'total' => $exam->attempts_count]) }}</span>
                                <div class="w-20 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden" role="progressbar" aria-valuenow="{{ $gradedPercent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="h-full bg-blue-600" style="width: {{ $gradedPercent }}%"></div>
                                </div>
                            </div>
                        @endif
                        <a href="{{ route('lecturer.results.index', $exam) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 text-xs">{{ __('Grade') }}</a>
                    </td>
                    <td class="px-4 py-2 text-right text-sm whitespace-nowrap space-x-4">
                        <a href="{{ route('lecturer.exams.show', $exam) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('Edit') }}</a>

                        <div x-data class="inline">
                            @if ($counts['total'] === 0)
                                <button type="button" disabled class="text-red-300 dark:text-red-800 text-sm opacity-50 cursor-not-allowed">{{ __('Reset submissions') }}</button>
                            @else
                                <form method="POST" action="{{ route('lecturer.exams.submissions.reset', $exam) }}" class="inline" x-ref="resetSubmissionsForm{{ $exam->id }}" @submit.prevent="$dispatch('open-modal', 'reset-submissions-{{ $exam->id }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400 text-sm">{{ __('Reset submissions') }}</button>
                                </form>

                                <x-confirm-modal
                                    name="reset-submissions-{{ $exam->id }}"
                                    :title="__('Reset exam submissions?')"
                                    :body="$resetBody"
                                    :confirm-label="__('Reset :count submissions', ['count' => $counts['total']])"
                                    x-on:click="$refs['resetSubmissionsForm{{ $exam->id }}'].submit()"
                                />
                            @endif
                        </div>

                        @if (! $exam->is_published)
                            <div x-data class="inline">
                                <form method="POST" action="{{ route('lecturer.exams.destroy', $exam) }}" class="inline" x-ref="deleteExamForm{{ $exam->id }}" @submit.prevent="$dispatch('open-modal', 'delete-exam-{{ $exam->id }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400">{{ __('Delete') }}</button>
                                </form>

                                <x-confirm-modal
                                    name="delete-exam-{{ $exam->id }}"
                                    :title="__('Delete exam?')"
                                    :body="__('This permanently removes “:name” and all its questions. This cannot be undone.', ['name' => $exam->title])"
                                    confirm-label="Delete"
                                    x-on:click="$refs['deleteExamForm{{ $exam->id }}'].submit()"
                                />
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('No exams yet for this subject.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
