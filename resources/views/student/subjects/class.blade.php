<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ $subject->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Subject detail card — code/name, lecturer(s), the acting
                 student's own enrolled class. --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ __('Subject') }}</h3>
                <p class="text-sm text-gray-900 dark:text-gray-100">{{ $subject->code }} &mdash; {{ $subject->name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Lecturer') }}: {{ $subject->lecturers->pluck('name')->join(', ') ?: __('Unassigned') }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Your class') }}: {{ $enrolledSection->name }}
                </p>
            </div>

            {{-- Exam list card — a SEPARATE card from the subject detail
                 (TAK-07). Only exams from Exam::visibleTo() reach this
                 view; the server never filters by availability (ENR-08 — every
                 visible exam is sent with a status label). Unavailable-and-
                 unattempted exams are only COLLAPSED client-side by default
                 (still in the DOM, revealed by the "show all" toggle), so
                 ENR-08's "never hidden server-side" guarantee is untouched. --}}
            <div x-data="{ showAll: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">{{ __('Exams') }}</h3>

                @php
                    // An exam is "unavailable" when it is not currently open (coming
                    // soon or closed). Those are hidden by default UNLESS the student
                    // already has an attempt on them (they still need Resume/result
                    // access). The toggle below reveals the hidden ones.
                    $hiddenCount = $exams->filter(fn ($e) => $e->availabilityState() !== 'available' && $e->attempts->first() === null)->count();
                    $visibleByDefault = $exams->count() - $hiddenCount;
                @endphp

                @forelse ($exams as $exam)
                    @php
                        // AVL-01/AVL-03: display-only — never filtered on server-side.
                        $availabilityState = $exam->availabilityState();
                        $isAvailable = $availabilityState === 'available';
                        $availabilityLabel = match ($availabilityState) {
                            'opening' => __('Opens :date', ['date' => $exam->available_from?->format('M j, Y g:ia')]),
                            'closed' => __('Closed'),
                            default => __('Available'),
                        };
                        // The acting student's own attempt (user-scoped, eager-loaded;
                        // the single-attempt constraint guarantees at most one row).
                        $ownAttempt = $exam->attempts->first();
                        // Hidden until "show all" unless available or already attempted.
                        $isDefaultHidden = ! $isAvailable && $ownAttempt === null;
                    @endphp
                    <div class="py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 flex items-start justify-between gap-4" @if ($isDefaultHidden) x-show="showAll" x-cloak @endif>
                        {{-- Left column: exam details. --}}
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $exam->title }}</span>
                                <x-status-pill :status="$availabilityState">{{ $availabilityLabel }}</x-status-pill>
                            </div>

                            {{-- Duration + availability window / deadline at a glance. --}}
                            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ __('Duration') }}: {{ $exam->duration_minutes }} {{ __('min') }}</span>
                                @if ($exam->available_from)
                                    <span>{{ __('Opens') }}: {{ $exam->available_from->format('M j, Y · g:ia') }}</span>
                                @endif
                                @if ($exam->available_until)
                                    <span>{{ __('Deadline') }}: {{ $exam->available_until->format('M j, Y · g:ia') }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Right column: the action button. --}}
                        <div class="shrink-0 w-32 sm:w-40">
                            @if ($ownAttempt === null)
                                @if ($isAvailable)
                                    {{-- Open + not attempted — enabled Start. --}}
                                    <a href="{{ route('student.exams.show', $exam) }}" class="flex w-full justify-center items-center px-5 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        {{ __('Start') }}
                                    </a>
                                @else
                                    {{-- Unavailable (coming soon / closed) — disabled, cannot start. --}}
                                    <button type="button" disabled class="flex w-full justify-center items-center px-5 py-2.5 bg-gray-100 text-gray-400 text-sm font-medium rounded-lg border border-gray-200 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500 dark:border-gray-600">
                                        {{ $availabilityState === 'opening' ? __('Not open yet') : __('Closed') }}
                                    </button>
                                @endif
                            @elseif ($ownAttempt->status === 'in_progress')
                                <a href="{{ route('student.attempts.show', $ownAttempt) }}" class="flex w-full justify-center items-center px-5 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    {{ __('Resume') }}
                                </a>
                            @elseif ($ownAttempt->status === 'graded')
                                {{-- Graded — navigating to the result is meaningful (there's a score to see). --}}
                                <a href="{{ route('student.attempts.result', $ownAttempt) }}" class="flex w-full justify-center items-center px-5 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    {{ __('View result') }}
                                </a>
                            @else
                                {{-- Submitted, awaiting grading — a popup explains the state
                                     instead of navigating to an empty result page (issue #6). --}}
                                <button type="button" data-modal-target="awaiting-grading-modal" data-modal-toggle="awaiting-grading-modal" class="flex w-full justify-center items-center px-5 py-2.5 bg-white hover:bg-gray-100 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 dark:border-gray-600 dark:focus:ring-gray-700">
                                    {{ __('Awaiting grading') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No exams yet.') }}</p>
                @endforelse

                {{-- When everything is unavailable, the list looks empty until toggled. --}}
                @if ($visibleByDefault === 0 && $exams->isNotEmpty())
                    <p x-show="! showAll" class="text-sm text-gray-500 dark:text-gray-400">{{ __('No exams are open right now.') }}</p>
                @endif

                {{-- Toggle: unavailable (coming soon / closed, unattempted) exams are
                     hidden by default to keep the list focused on what's takeable now. --}}
                @if ($hiddenCount > 0)
                    <div class="mt-4">
                        <button type="button" @click="showAll = ! showAll" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">
                            <span x-show="! showAll">{{ __('Show unavailable exams (:count)', ['count' => $hiddenCount]) }}</span>
                            <span x-show="showAll" x-cloak>{{ __('Hide unavailable exams') }}</span>
                        </button>
                    </div>
                @endif
            </div>

            <x-back-button :href="route('student.home')">{{ __('Back to your subjects') }}</x-back-button>
        </div>
    </div>

    {{-- Shared Flowbite popup for any "Awaiting grading" exam above (issue #6). One
         instance serves every submitted-but-ungraded row; the message is generic. --}}
    <div id="awaiting-grading-modal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-md max-h-full">
            <div class="relative bg-white rounded-lg shadow dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between p-4 md:p-5 border-b border-gray-200 rounded-t dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Awaiting grading') }}</h3>
                    <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-hide="awaiting-grading-modal">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                        <span class="sr-only">{{ __('Close') }}</span>
                    </button>
                </div>
                <div class="p-4 md:p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('You have submitted this exam. Your result will be available here once your lecturer finishes grading it.') }}
                    </p>
                </div>
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b dark:border-gray-700">
                    <button data-modal-hide="awaiting-grading-modal" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">{{ __('Got it') }}</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
