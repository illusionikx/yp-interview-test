<x-app-layout>
    {{--
        Screen 2 (05-UI-SPEC.md) — lecturer per-exam results index (GRD-05,
        D-06). Reuses the exams/index.blade.php table pattern verbatim
        (max-w-7xl, min-w-full divide-y table, colspan empty-row).
    --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ $exam->title }} &mdash; {{ __('Results') }}
        </h2>
    </x-slot>

    @php
        // Same trim-a-decimal-cast-string convention as the show.blade.php
        // breakdown view, so "4.00" renders as "4" here too.
        $formatNumber = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{--
                GRD-06 grading header — exam details + a bounded grading-
                progress aggregate (never a per-attempt loop; $progress is
                computed once in ResultController::index via
                AttemptVoider::summarize()).
            --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $exam->title }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $exam->subject->name }} &middot; {{ $exam->duration_minutes }} {{ __('min') }}
                        </p>
                    </div>
                    <x-status-pill :status="$exam->is_published ? 'published' : 'draft'">
                        {{ $exam->is_published ? __('Published') : __('Draft') }}
                    </x-status-pill>
                </div>

                <div>
                    @if ($progress['gradableTotal'] === 0)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No submissions to grade yet.') }}</p>
                    @else
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Grading progress') }}:
                            <span class="font-semibold tabular-nums">{{ $progress['graded'] }} / {{ $progress['gradableTotal'] }}</span>
                            {{ __('graded') }}
                            @if ($progress['needingGrading'] > 0)
                                <span class="text-amber-700 dark:text-amber-400">({{ $progress['needingGrading'] }} {{ __('needing grading') }})</span>
                            @endif
                        </p>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                                class="bg-green-600 h-2 rounded-full"
                                style="width: {{ (int) round(($progress['graded'] / $progress['gradableTotal']) * 100) }}%"
                            ></div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Student') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Score') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($attempts as $attempt)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $attempt->user->name }}</td>
                                <td class="px-4 py-2 text-sm">
                                    @if ($attempt->status === 'graded')
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">{{ __('Graded') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300">{{ __('Submitted') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 tabular-nums">
                                    {{-- Never a partial number while pending (Pitfall 3 applied to the list). --}}
                                    @if ($attempt->status === 'graded')
                                        {{ $formatNumber($attempt->score) }} / {{ $formatNumber($totalPossible) }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <a href="{{ route('lecturer.results.show', [$exam, $attempt]) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">
                                        {{ $attempt->status === 'graded' ? __('View') : __('Grade') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('No submissions yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-back-button :href="route('lecturer.subjects.manage', ['subject' => $exam->subject_id]) . '?tab=exams'">{{ __('Back to exams') }}</x-back-button>
        </div>
    </div>
</x-app-layout>
