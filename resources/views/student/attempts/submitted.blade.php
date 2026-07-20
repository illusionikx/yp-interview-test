<x-app-layout>
    {{--
        Screen 4 (04-UI-SPEC.md) — a calm, score-free confirmation. Grading
        and results are entirely Phase 5; this page must never render or
        fetch anything score/grade-related.
    --}}
    <div class="py-16">
        <div class="max-w-md mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>

                <h2 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white">{{ __('Exam submitted') }}</h2>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __("Your answers have been recorded. Your lecturer will review your results, and you'll be able to view your score once grading is complete.") }}
                </p>

                <a href="{{ route('student.exams.index') }}" class="mt-6 inline-block text-sm text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 underline">
                    {{ __('Back to my exams') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
