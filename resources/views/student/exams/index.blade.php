<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('My exams') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- CR-02: the acting student's own in-progress attempts whose
                 exam has dropped out of Exam::visibleTo() (withdrawal,
                 rejection, unpublish, un-assignment) — resolved via an
                 ownership-driven query (App\Http\Controllers\Student\
                 ExamController@index), never visibleTo(), so this section
                 can never be hidden by the same conditions that hid the
                 exam itself. Links straight to the ownership-gated
                 attempts.show route (AttemptPolicy::view() is
                 ownership-only), bypassing the exam landing page entirely. --}}
            @if ($resumableAttempts->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('In-progress attempts') }}</h3>
                    @foreach ($resumableAttempts as $resumableAttempt)
                        <div class="py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                            <a href="{{ route('student.attempts.show', $resumableAttempt) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 font-medium underline">
                                {{ __('Resume exam') }}: {{ $resumableAttempt->exam->title }}
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Only exams from Exam::visibleTo($request->user()) reach this view —
                 published AND assigned to a section the student is enrolled in.
                 Grouped by subject; each subject is its own card with a client-side
                 toggle that reveals unavailable-and-unattempted exams (same pattern
                 as the per-class list). The server never filters by availability
                 (ENR-08) — hiding is display-only, collapsed in the DOM. --}}
            @forelse ($exams->groupBy(fn ($e) => $e->subject->name) as $subjectName => $subjectExams)
                @php
                    // An exam is default-hidden when it is not currently open AND the
                    // student has no attempt on it (a finished/in-progress attempt still
                    // needs its result/resume link visible).
                    $hiddenCount = $subjectExams->filter(fn ($e) => $e->availabilityState() !== 'available' && $e->attempts->first() === null)->count();
                @endphp
                <div x-data="{ showAll: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">{{ $subjectName }}</h3>

                    @foreach ($subjectExams as $exam)
                        @php
                            // AVL-01/AVL-03: display-only — never filtered on the server.
                            $availabilityState = $exam->availabilityState();
                            $availabilityLabel = match ($availabilityState) {
                                'opening' => __('Opens :date', ['date' => $exam->available_from?->format('M j, Y g:ia')]),
                                'closed' => __('Closed'),
                                default => __('Available'),
                            };
                            // The acting student's own attempt (user-scoped, eager-loaded;
                            // the single-attempt constraint guarantees at most one). Only a
                            // finished attempt gets a result link (D-05/GRD-03 still gate
                            // the score on the result page itself).
                            $ownAttempt = $exam->attempts->first();
                            $finishedAttempt = $ownAttempt && $ownAttempt->status !== 'in_progress' ? $ownAttempt : null;
                            $isDefaultHidden = $availabilityState !== 'available' && $ownAttempt === null;
                        @endphp
                        <div class="py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0" @if ($isDefaultHidden) x-show="showAll" x-cloak @endif>
                            <a href="{{ route('student.exams.show', $exam) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 font-medium">
                                {{ $exam->title }}
                            </a>
                            <x-status-pill :status="$availabilityState">{{ $availabilityLabel }}</x-status-pill>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $exam->duration_minutes }} {{ __('minutes') }}
                            </p>
                            @if ($finishedAttempt)
                                <a href="{{ route('student.attempts.result', $finishedAttempt) }}" class="mt-1 inline-block text-sm text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 underline">
                                    {{ __('View result') }}
                                </a>
                            @endif
                        </div>
                    @endforeach

                    {{-- Every exam in this subject is hidden by default — the card looks
                         empty until toggled. --}}
                    @if ($hiddenCount === $subjectExams->count())
                        <p x-show="! showAll" class="text-sm text-gray-500 dark:text-gray-400">{{ __('No exams are open right now.') }}</p>
                    @endif

                    @if ($hiddenCount > 0)
                        <div class="mt-4">
                            <button type="button" @click="showAll = ! showAll" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">
                                <span x-show="! showAll">{{ __('Show unavailable exams (:count)', ['count' => $hiddenCount]) }}</span>
                                <span x-show="showAll" x-cloak>{{ __('Hide unavailable exams') }}</span>
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No exams are available for you yet.') }}</p>
                </div>
            @endforelse

            <a href="{{ route('student.home') }}" class="text-sm text-gray-600 dark:text-gray-400 underline">{{ __('Back to student area') }}</a>
        </div>
    </div>
</x-app-layout>
