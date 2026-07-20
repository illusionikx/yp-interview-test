<x-app-layout>
    {{--
        Screen 3 (05-UI-SPEC.md) — two mutually-exclusive gated states.
        State A (awaiting): zero score data anywhere in the response
        (05-RESEARCH.md Pitfall 3). State B (graded): total + per-question
        breakdown, strictly read-only, never the correct option's identity
        for a wrong answer (D-07).
    --}}
    @if ($awaiting)
        <div class="py-16">
            <div class="max-w-md mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>

                    <h2 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white">{{ __('Awaiting grading') }}</h2>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ __("Your submission has been recorded. Your lecturer still needs to grade one or more open-text answers before your final score is available.") }}
                    </p>

                    <a href="{{ route('student.exams.index') }}" class="mt-6 inline-block text-sm text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 underline">
                        {{ __('Back to my exams') }}
                    </a>
                </div>
            </div>
        </div>
    @else
        @php
            // Trim a decimal-cast numeric string down to its plain integer/
            // decimal form ("2.00" -> "2", "2.50" -> "2.5") — never render
            // trailing precision the UI-SPEC's "{score} / {total}" copy
            // doesn't call for.
            $formatNumber = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
        @endphp

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Your Result') }}</h2>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                        {{ $formatNumber($totalAwarded) }} / {{ $formatNumber($totalPossible) }} {{ __('points') }}
                    </p>
                </div>

                <div class="space-y-6">
                    @foreach ($breakdown as $index => $item)
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Question :n of :total · :points :label', [
                                    'n' => $index + 1,
                                    'total' => $breakdown->count(),
                                    'points' => $item['points'],
                                    'label' => $item['points'] === 1 ? __('point') : __('points'),
                                ]) }}
                            </p>

                            <p class="mt-1 font-semibold text-gray-800 dark:text-gray-200">{{ $item['body'] }}</p>

                            <p class="mt-3 text-sm text-gray-800 dark:text-gray-200">
                                {{ $item['student_answer'] ?? __('No answer submitted') }}
                            </p>

                            @if ($item['type'] === 'mcq')
                                @if ($item['is_correct'])
                                    <p class="mt-2 text-sm font-medium text-green-700 dark:text-green-400">{{ __('✓ Correct') }}</p>
                                @else
                                    <p class="mt-2 text-sm font-medium text-red-700 dark:text-red-400">{{ __('✗ Incorrect') }}</p>
                                @endif
                            @else
                                <span class="mt-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                    {{ $formatNumber($item['score_awarded']) }} / {{ $formatNumber($item['points']) }} {{ __('points') }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('student.exams.index') }}" class="inline-block text-sm text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 underline">
                    {{ __('Back to my exams') }}
                </a>
            </div>
        </div>
    @endif
</x-app-layout>
