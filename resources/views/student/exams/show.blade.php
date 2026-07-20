<x-app-layout>
    @php
        // AVL-02: display-only availability state — never a gate. The
        // actual enforcement lives in AttemptController@store's
        // isAvailableNow() branch; this page must stay reachable
        // regardless of what state this resolves to.
        $availabilityState = $exam->availabilityState();
        $availabilityLabel = match ($availabilityState) {
            'opening' => __('Opens :date', ['date' => $exam->available_from?->format('M j, Y g:ia')]),
            'closed' => __('Closed'),
            default => __('Available'),
        };
    @endphp

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                {{ $exam->title }}
            </h2>
            <x-status-pill :status="$availabilityState">{{ $availabilityLabel }}</x-status-pill>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            {{-- Read-only landing — title/subject/duration/question count only.
                 Never load or render questions/options here. --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-xl">
                {{-- Card header: subject + exam title. --}}
                <div class="px-8 py-6 border-b border-gray-200 dark:border-gray-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ $exam->subject->name }}</p>
                    <h3 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $exam->title }}</h3>
                    @if ($exam->description)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $exam->description }}</p>
                    @endif
                </div>

                {{-- Key facts as a scannable grid instead of a flat list of lines. --}}
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-gray-200 dark:bg-gray-700">
                    <div class="bg-white dark:bg-gray-800 px-8 py-5">
                        <dt class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>
                            {{ __('Duration') }}
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $exam->duration_minutes }} {{ __('minutes') }}</dd>
                    </div>

                    <div class="bg-white dark:bg-gray-800 px-8 py-5">
                        <dt class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            {{ __('Questions') }}
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $exam->questions_count }}</dd>
                    </div>

                    @if ($exam->available_from || $exam->available_until)
                        <div class="bg-white dark:bg-gray-800 px-8 py-5">
                            <dt class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                {{ __('Availability') }}
                            </dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                @if ($exam->available_from && $exam->available_until)
                                    {{ $exam->available_from->format('M j, Y g:ia') }} &ndash; {{ $exam->available_until->format('M j, Y g:ia') }}
                                @elseif ($exam->available_from)
                                    {{ __('From :date', ['date' => $exam->available_from->format('M j, Y g:ia')]) }}
                                @else
                                    {{ __('Until :date', ['date' => $exam->available_until->format('M j, Y g:ia')]) }}
                                @endif
                            </dd>
                        </div>
                    @endif

                    @if ($enrolledSection)
                        <div class="bg-white dark:bg-gray-800 px-8 py-5">
                            <dt class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-2-5.24"/></svg>
                                {{ __('Your section') }}
                            </dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $enrolledSection->name }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Timer warning: the clock starts on Proceed and cannot be paused. --}}
                <div class="px-8 pt-6">
                    <div class="flex items-start gap-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3">
                        <svg class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('Once you proceed, the timer starts and cannot be paused. Your answers are saved as you go and submitted automatically when time runs out.') }}</p>
                    </div>
                </div>

                @php
                    // D-10: the Phase-3 disabled seam — kept intact per plan
                    // instruction. It no longer drives the label (that's
                    // always "Proceed" now, per the locked Copywriting
                    // Contract, 08-UI-SPEC.md) but the POST form/target is
                    // unchanged, and the button stays enabled regardless of
                    // availability — the server refuses and flashes; a
                    // disabled button is never the enforcement mechanism.
                    $hasInProgressAttempt = $exam->attempts()
                        ->where('user_id', auth()->id())
                        ->where('status', 'in_progress')
                        ->exists();
                @endphp
                <div class="px-8 py-6 flex items-center gap-4">
                    <form method="POST" action="{{ route('student.attempts.store', $exam) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            {{ __('Proceed') }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </button>
                    </form>
                    <a href="{{ route('student.exams.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 underline">{{ __('Back') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
