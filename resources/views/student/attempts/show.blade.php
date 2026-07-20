<x-app-layout>
    {{--
        Take page (Screen 1, 04-UI-SPEC.md). The controller has already run
        finalizeIfExpired() first (D-04/D-05) — if this view renders at all,
        the attempt is still in_progress. Options render body text only,
        via the explicit column-whitelisted view-model the controller built
        (id/question_id/body only — the answer-key flag is never selected,
        D-07/TAK-06).

        The whole page lives inside one outer attemptTimer() Alpine scope
        (04-04) so the live countdown's `autoSubmitting` flag is reachable
        from every nested per-question card to disable its inputs, and so
        a per-question 422 (deadline crossed mid-request) can drive the
        exact same auto-submit transition as the countdown hitting zero —
        via a bubbled `deadline-expired` window event, not direct nested-
        scope coupling (Interaction Contract rule 1: the countdown itself
        is purely cosmetic and never gates inputs; only an actual expiry —
        zero reached, or the server's 422 — does).
    --}}
    @php
        // FIX-01: the set of questions with a saved answer at page-load,
        // seeded into the page-level attemptTimer() Alpine scope so the
        // submit-confirmation modal's answered count stays reactive as the
        // session progresses (not frozen at this initial snapshot).
        $answeredQuestionIds = $savedAnswers->keys()->all();

        // Informational only (UI-SPEC Interaction Contract rule 5) — never
        // gates the Submit button; an unanswered question never blocks
        // submission. Server-rendered fallback for a no-JS render; both the
        // sticky top bar (TAK-09) and the submit modal (FIX-01) bind the
        // live value via x-text off the page-level answeredCount instead.
        // Hoisted above the header so both consumers can read it.
        $answeredCount = $savedAnswers->count();
    @endphp

    <div
        x-data="attemptTimer(
            {{ $remainingSeconds }},
            '{{ route('student.attempts.submit', $attempt) }}',
            '{{ route('student.attempts.submitted', $attempt) }}',
            @js($answeredQuestionIds)
        )"
        x-init="init(); start()"
        x-on:deadline-expired.window="autoSubmit()"
        x-on:question-answered.window="markAnswered($event.detail.questionId)"
    >
        {{-- Screen 3: Auto-Submit Transition (04-UI-SPEC.md) — full-width,
             NOT a modal (no dismiss, no focus trap), pinned above the
             sticky header. --}}
        <div
            x-show="autoSubmitting"
            class="fixed inset-x-0 top-0 z-20 bg-gray-800 text-white text-center text-sm font-medium py-2 px-4"
        >
            {{ __("Time's up — submitting your exam…") }}
        </div>

        {{-- One-shot assertive announcement, written only on a countdown
             state transition (normal→warning→critical) — never on every
             per-second tick (UI-SPEC Interaction Contract rule 7). --}}
        <div class="sr-only" aria-live="assertive" x-text="announcement"></div>

        {{--
            TAK-09: sticky top bar carrying subject + exam name, the live
            timer (untouched), and answered/total progress, plus an
            Instructions button that opens a popup (x-modal, never a native
            alert). Exam DETAILS (subject/duration/question count) render
            inline here and in the popup body; INSTRUCTIONS live only
            behind the popup — the two read as separate things per
            13-CONTEXT.md.
        --}}
        <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 shadow">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">
                            {{ $attempt->exam->subject->name }}
                        </p>
                        <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200 leading-tight truncate">
                            {{ $attempt->exam->title }}
                        </h1>
                    </div>
                    <span
                        class="shrink-0 text-3xl font-semibold tabular-nums rounded-md px-3 py-1"
                        x-text="display"
                        x-bind:class="badgeClasses()"
                    >{{ sprintf('%02d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60) }}</span>
                </div>

                <div class="mt-3 flex items-center justify-between gap-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="answeredCount">{{ $answeredCount }}</span>
                        {{ __('of') }} {{ count($questions) }} {{ __('answered') }}
                    </p>

                    <button
                        type="button"
                        @click="$dispatch('open-modal', 'instructions')"
                        class="shrink-0 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500 underline"
                    >
                        {{ __('Instructions') }}
                    </button>
                </div>
            </div>
        </div>

        @php
            // Same URL for every question card below — computed once (D-06,
            // 04-RESEARCH.md Pattern 4). window.axios (resources/js/bootstrap.js)
            // already attaches Laravel's CSRF cookie automatically.
            $answerUrl = route('student.attempts.answer', $attempt);
        @endphp

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                @if (count($questions) === 0)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 text-center">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Nothing to answer') }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('This exam has no questions yet. You can still submit it.') }}</p>
                    </div>
                @else
                    {{--
                        TAK-10: vertical stepper (left rail on lg+, a
                        horizontally-scrollable strip on mobile) + the
                        question cards (main column). Both stay inside the
                        outer attemptTimer() scope so the stepper can read
                        the SAME reactive `answered` map the header progress
                        and submit modal already read — no new client-only
                        answered state is introduced.
                    --}}
                    <div class="lg:flex lg:items-start lg:gap-6">
                        <nav aria-label="{{ __('Question navigation') }}" class="mb-6 lg:mb-0 lg:w-48 lg:shrink-0 lg:sticky lg:top-40">
                            <ol class="flex lg:flex-col gap-2 overflow-x-auto lg:overflow-visible bg-white dark:bg-gray-800 rounded-lg shadow-sm p-3">
                                @foreach ($questions as $index => $question)
                                    <li class="shrink-0">
                                        <a
                                            href="#question-{{ $question['id'] }}"
                                            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                                        >
                                            <span
                                                class="inline-flex items-center justify-center w-6 h-6 shrink-0 rounded-full border text-xs font-medium border-gray-300 dark:border-gray-600"
                                                x-bind:class="answered[{{ $question['id'] }}] ? 'bg-green-50 dark:bg-green-900 border-green-400 text-green-700 dark:text-green-300' : ''"
                                            >
                                                <span x-show="answered[{{ $question['id'] }}]" x-cloak>&check;</span>
                                                <span x-show="!answered[{{ $question['id'] }}]">{{ $index + 1 }}</span>
                                            </span>
                                            <span class="whitespace-nowrap">{{ __('Question') }} {{ $index + 1 }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        </nav>

                        <div class="flex-1 min-w-0 space-y-6">
                    @foreach ($questions as $index => $question)
                        @php
                            $savedAnswer = $savedAnswers[$question['id']] ?? null;
                        @endphp
                        {{--
                            One small Alpine scope per card, holding only this
                            card's own save status — deliberately NOT a single
                            whole-page x-data JSON blob of every question (that
                            is the Pitfall-3 answer-key leak vector,
                            04-RESEARCH.md). @change fires the MCQ save
                            immediately; @blur fires the open-text save (UI-SPEC
                            Interaction Contract rule 3 — no per-keystroke POSTs).
                            A 422 here (deadline crossed mid-request) dispatches
                            a window event so the outer countdown scope drives
                            the same auto-submit transition (UI-SPEC Copywriting
                            "Deadline-rejected write" entry).
                        --}}
                        <div
                            id="question-{{ $question['id'] }}"
                            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 scroll-mt-40 flex gap-4"
                            x-data="{
                                status: 'idle',
                                lastPayload: null,
                                save(payload) {
                                    this.lastPayload = payload;
                                    this.status = 'saving';
                                    window.axios.post('{{ $answerUrl }}', { question_id: {{ $question['id'] }}, ...payload })
                                        .then(() => {
                                            this.status = 'saved';
                                            // Bubble to the page-level attemptTimer() scope (mirrors the
                                            // existing deadline-expired idiom) rather than reaching into
                                            // it directly — keeps this card's scope isolated (Phase-4
                                            // no-leak invariant, see file header comment).
                                            window.dispatchEvent(new CustomEvent('question-answered', {
                                                detail: { questionId: {{ $question['id'] }} },
                                            }));
                                        })
                                        .catch((error) => {
                                            // Only a genuine deadline rejection (server sets `expired: true`)
                                            // drives the auto-submit transition. An ordinary validation 422
                                            // (e.g. a stale option id) must NOT force-finalize a still-valid
                                            // attempt — it just shows 'save failed' + a retry (review blocker).
                                            if (error.response && error.response.status === 422
                                                && error.response.data && error.response.data.expired) {
                                                this.status = 'expired';
                                                window.dispatchEvent(new CustomEvent('deadline-expired'));
                                            } else {
                                                this.status = 'failed';
                                            }
                                        });
                                },
                                retry() {
                                    if (this.lastPayload) {
                                        this.save(this.lastPayload);
                                    }
                                },
                            }"
                        >
                            {{-- Left gutter: the question-number badge (consistent with the
                                 exam editor's numbered left column). --}}
                            <div class="shrink-0">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-50 dark:bg-blue-900/40 text-sm font-semibold text-blue-700 dark:text-blue-300">{{ $index + 1 }}</span>
                            </div>

                            <div class="flex-1 min-w-0 space-y-3">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $question['points'] }} {{ (int) $question['points'] === 1 ? __('point') : __('points') }}
                            </p>

                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $question['body'] }}</p>

                            @if ($question['type'] === 'mcq')
                                <fieldset>
                                    <legend class="sr-only">{{ $question['body'] }}</legend>
                                    <div class="space-y-2">
                                        @foreach ($question['options'] as $option)
                                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                <input
                                                    type="radio"
                                                    name="question_{{ $question['id'] }}"
                                                    value="{{ $option->id }}"
                                                    @checked($savedAnswer && (int) $savedAnswer->selected_option_id === $option->id)
                                                    @change="save({ selected_option_id: $event.target.value })"
                                                    x-bind:disabled="autoSubmitting"
                                                    class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-blue-600 focus:ring-blue-500"
                                                >
                                                {{ $option->body }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @else
                                <label for="question_{{ $question['id'] }}" class="sr-only">{{ $question['body'] }}</label>
                                <textarea
                                    id="question_{{ $question['id'] }}"
                                    name="question_{{ $question['id'] }}"
                                    rows="4"
                                    @blur="save({ answer_text: $event.target.value })"
                                    x-bind:disabled="autoSubmitting"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
                                >{{ $savedAnswer->answer_text ?? '' }}</textarea>
                            @endif

                            {{-- Autosave status tag (UI-SPEC Status Colors) — scoped to this
                                 card only; a failure here never blocks other questions. --}}
                            <div class="flex justify-end" aria-live="polite">
                                <span x-show="status === 'saving'" class="text-sm text-gray-500 dark:text-gray-400">{{ __('Saving…') }}</span>
                                <span x-show="status === 'saved'" class="text-sm text-green-700 dark:text-green-400">&check; {{ __('Saved') }}</span>
                                <span x-show="status === 'expired'" class="text-sm text-red-600 dark:text-red-400">{{ __("Time's up — your exam is being submitted.") }}</span>
                                <button type="button" x-show="status === 'failed'" x-bind:disabled="autoSubmitting" @click="retry()" class="text-sm text-red-600 dark:text-red-400 underline">
                                    {{ __('Save failed — Retry') }}
                                </button>
                            </div>
                            </div>
                        </div>
                    @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-12 flex justify-end">
                    <x-primary-button type="button" x-bind:disabled="autoSubmitting" @click="$dispatch('open-modal', 'confirm-submit')">
                        {{ __('Submit Exam') }}
                    </x-primary-button>
                </div>
            </div>
        </div>

        <x-modal name="confirm-submit" :show="false" maxWidth="sm" focusable>
            <form method="POST" action="{{ route('student.attempts.submit', $attempt) }}" class="p-6" x-on:submit="submitting = true; detachBeforeUnload()">
                @csrf

                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Submit this exam?') }}
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __("You won't be able to change your answers after this.") }}
                    <span x-text="`${answeredCount} of {{ count($questions) }} questions answered.`">{{ __(':answered of :total questions answered.', ['answered' => $answeredCount, 'total' => count($questions)]) }}</span>
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button type="button" @click="$dispatch('close')">
                        {{ __('Keep Working') }}
                    </x-secondary-button>

                    <x-primary-button type="submit" x-bind:disabled="autoSubmitting">
                        {{ __('Yes, Submit') }}
                    </x-primary-button>
                </div>
            </form>
        </x-modal>

        {{--
            TAK-09: exam DETAILS (subject/duration/question count) repeat
            here for quick reference, but the INSTRUCTIONS body below is
            what this popup exists for — the two read as separate sections,
            never merged into one paragraph.
        --}}
        <x-modal name="instructions" :show="false" maxWidth="lg" focusable>
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Exam Details') }}
                </h2>

                <dl class="mt-3 grid grid-cols-3 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Subject') }}</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $attempt->exam->subject->name }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Duration') }}</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ __(':minutes minutes', ['minutes' => $attempt->exam->duration_minutes]) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">{{ __('Questions') }}</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ count($questions) }}</dd>
                    </div>
                </dl>

                <h3 class="mt-6 text-base font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Instructions') }}
                </h3>

                <ul class="mt-2 list-disc list-inside space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>{{ __('Your answers are saved automatically as you go — there is no separate save button.') }}</li>
                    <li>{{ __('The timer is enforced by the server; your exam is submitted automatically when time runs out.') }}</li>
                    <li>{{ __('You can submit your exam only once.') }}</li>
                    <li>{{ __('Answer options always stay in the same fixed order shown on this page.') }}</li>
                </ul>

                @if ($attempt->exam->description)
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ $attempt->exam->description }}</p>
                @endif

                <div class="mt-6 flex justify-end">
                    <x-secondary-button type="button" @click="$dispatch('close')">
                        {{ __('Close') }}
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>

        {{--
            TAK-11: 10-minute one-shot toaster — styled like the app's
            single toast convention (<x-toast>: border-l accent, dismissible,
            fixed top-right). x-show-bound to a flag that the fired-once
            guard in attemptTimer() sets AT MOST once (see the script's
            checkTenMinuteWarning()) — never re-derived per tick.
        --}}
        <div
            x-show="showTenMinuteToast"
            x-transition
            x-cloak
            role="alert"
            class="fixed top-20 right-4 z-50 flex items-start gap-2 w-full max-w-xs p-4 bg-neutral-primary-soft text-body border border-default border-l-4 border-l-red-400 dark:border-l-red-600 rounded-base shadow-xs"
        >
            <svg class="shrink-0 w-5 h-5 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>

            <div class="text-sm font-normal flex-1">{{ __('10 minutes remaining.') }}</div>

            <button
                type="button"
                @click="showTenMinuteToast = false"
                aria-label="{{ __('Dismiss') }}"
                class="shrink-0 inline-flex items-center justify-center rounded-base p-1 text-gray-400 hover:bg-neutral-secondary-medium hover:text-gray-600 dark:hover:text-gray-300"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{--
        Live countdown (TAK-04/TAK-02, 04-RESEARCH.md Pattern 2 extended).
        Seeded ONCE from the server's remaining_seconds at page load; ticks
        down purely client-side via setInterval — never re-fetches the
        true remaining time (Interaction Contract rule 1). Thresholds
        (300s/60s) are fixed absolute values (UI-SPEC Status Colors).
        At zero — or on a 422 bubbled up from any per-question autosave —
        autoSubmit() fires exactly once, POSTs the real submit route (the
        client half of the two-layer auto-submit; the server's
        finalizeIfExpired() remains authoritative even if this request
        never lands, D-05), then hard-redirects to the confirmation.
    --}}
    <script>
        function attemptTimer(remaining, submitUrl, submittedUrl, answeredQuestionIds) {
            return {
                remaining,
                // Absolute end time (client wall-clock ms), set once in init().
                // The countdown is derived from this, not from counting ticks —
                // see syncRemaining().
                endsAt: 0,
                display: '',
                bucket: 'normal',
                announcement: '',
                autoSubmitting: false,
                // Set true the instant an intentional exit begins (manual
                // submit or auto-submit). The beforeunload guard checks this
                // directly, so the "leave site?" dialog can never fire on a
                // real submit even if the listener-removal timing is racy.
                submitting: false,
                timerId: null,
                // TAK-11: fired-once guard, mirroring the FIX-01 reactive-
                // counter precedent and the announcement one-shot pattern
                // below (written on a state TRANSITION, never per tick).
                // tenMinuteWarned flips to true exactly once, the FIRST time
                // remaining reaches or drops below 600s; showTenMinuteToast
                // is the reactive flag the toaster markup binds x-show to.
                // Because the flag is set before the toast is revealed, and
                // checkTenMinuteWarning() is guarded on `! this.tenMinuteWarned`,
                // the toast can appear at most once regardless of how many
                // ticks land at or under the 600s threshold.
                tenMinuteWarned: false,
                showTenMinuteToast: false,
                // FIX-01: a plain reactive object (not a Set — Alpine's
                // reactivity does not track Set mutations) keyed by question
                // id, seeded from the server's saved-answer snapshot so the
                // initial render is correct before any JS runs. Each
                // question is counted at most once regardless of how many
                // times its card's save() resolves.
                answered: Object.fromEntries(answeredQuestionIds.map((id) => [id, true])),
                get answeredCount() {
                    return Object.keys(this.answered).length;
                },
                markAnswered(questionId) {
                    this.answered[questionId] = true;
                },
                init() {
                    // Anchor an absolute deadline to the client clock at page load.
                    // The countdown is then derived from wall-clock elapsed time
                    // (syncRemaining), NOT from counting setInterval ticks — because
                    // browsers throttle/suspend intervals in a backgrounded tab, so
                    // a tick-counting timer freezes while unfocused and shows the
                    // wrong time on refocus. The server's finalizeIfExpired() stays
                    // the authoritative deadline regardless of this client clock.
                    this.endsAt = Date.now() + this.remaining * 1000;
                    this.setBucket(false);
                    this.render();

                    // Snap to the true remaining time the instant the tab is
                    // refocused, rather than waiting up to a second for the next
                    // (possibly still-throttled) tick.
                    this._visibilityHandler = () => {
                        if (! document.hidden) {
                            this.tick();
                        }
                    };
                    document.addEventListener('visibilitychange', this._visibilityHandler);

                    // Covers the case where the page is loaded (or reloaded)
                    // with 10 minutes or less already remaining — the
                    // one-shot toast still fires exactly once, not only on
                    // a live tick crossing the threshold.
                    this.checkTenMinuteWarning();
                    // AVL-05: warn on tab-close/navigate-away while the attempt
                    // is in progress. Reaching this page already implies
                    // in-progress (AttemptController@show redirects away
                    // otherwise) but the guard is asserted here explicitly
                    // rather than relying on that upstream redirect as an
                    // invisible precondition. Purely UX — never an integrity
                    // control; the server-side deadline in finalizeIfExpired()
                    // is the real safeguard (T-08-08-FALSE).
                    //
                    // MUST be a stored NAMED handler reference, never an
                    // anonymous inline function, so detachBeforeUnload() can
                    // actually remove it (T-08-08-UX) — otherwise every
                    // intentional submit or auto-submit would still raise the
                    // dialog. Chrome removed support for a custom message
                    // string in Chrome 51, so no message is set here; the
                    // `returnValue = ''` line is only the legacy-compat idiom.
                    // Note: the dialog only fires after the page has received
                    // at least one user interaction ("sticky activation") —
                    // browser anti-abuse behavior, not something this app
                    // controls.
                    if (!this.autoSubmitting) {
                        this._beforeUnloadHandler = (event) => {
                            if (this.autoSubmitting || this.submitting) {
                                return;
                            }
                            event.preventDefault();
                            event.returnValue = '';
                        };
                        window.addEventListener('beforeunload', this._beforeUnloadHandler);
                    }
                },
                // Idempotent — safe to call twice, since both intentional
                // exit paths (submit form, auto-submit) can plausibly run in
                // sequence.
                detachBeforeUnload() {
                    if (this._beforeUnloadHandler) {
                        window.removeEventListener('beforeunload', this._beforeUnloadHandler);
                        this._beforeUnloadHandler = null;
                    }
                },
                start() {
                    this.timerId = setInterval(() => this.tick(), 1000);
                },
                tick() {
                    if (this.autoSubmitting) {
                        return;
                    }

                    this.syncRemaining();
                    this.render();
                    this.setBucket(true);
                    this.checkTenMinuteWarning();

                    if (this.remaining <= 0) {
                        this.autoSubmit();
                    }
                },
                // Derive remaining seconds from the absolute deadline (wall-clock)
                // instead of decrementing a counter — so a throttled/backgrounded
                // tab that stopped firing ticks still shows the correct time the
                // moment it refocuses.
                syncRemaining() {
                    this.remaining = Math.max(0, Math.round((this.endsAt - Date.now()) / 1000));
                },
                render() {
                    const minutes = Math.floor(this.remaining / 60).toString().padStart(2, '0');
                    const seconds = (this.remaining % 60).toString().padStart(2, '0');
                    this.display = `${minutes}:${seconds}`;
                },
                setBucket(announce) {
                    const next = this.remaining > 300 ? 'normal' : (this.remaining > 60 ? 'warning' : 'critical');

                    if (next === this.bucket) {
                        return;
                    }

                    this.bucket = next;

                    if (announce) {
                        this.announcement = next === 'critical'
                            ? @js(__('Less than one minute remaining.'))
                            : @js(__('Less than five minutes remaining.'));
                    }
                },
                // TAK-11: the one-shot 10-minute guard. Deliberately its own
                // method (not folded into setBucket(), whose 'warning'/
                // 'critical' buckets are keyed to the pre-existing 300s/60s
                // announcement thresholds) so the 600s red-timer threshold
                // stays independently correct regardless of bucket state.
                // this.tenMinuteWarned is set BEFORE showTenMinuteToast is
                // revealed, and the whole body is gated on
                // `! this.tenMinuteWarned` — so this can run on every tick
                // under 600s remaining and still only ever show the toast
                // once (mirrors the FIX-01 fired-once precedent).
                checkTenMinuteWarning() {
                    if (this.remaining <= 600 && ! this.tenMinuteWarned) {
                        this.tenMinuteWarned = true;
                        this.showTenMinuteToast = true;
                    }
                },
                badgeClasses() {
                    // TAK-11: red at 10 minutes (600s) remaining — a wider
                    // window than the 60s 'critical' bucket used for the
                    // sr-only announcement/pulse, so it is computed directly
                    // off `remaining` rather than off `this.bucket`. The
                    // final-minute pulse is kept as an additional treatment
                    // within the same red state.
                    if (this.remaining <= 600) {
                        return {
                            'bg-red-50 text-red-700 border border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-700': true,
                            'animate-pulse': this.remaining <= 60,
                        };
                    }

                    return {
                        'bg-blue-50 text-gray-800 dark:bg-blue-900 dark:text-blue-200': this.bucket === 'normal',
                        'bg-amber-50 text-amber-700 border border-amber-300 dark:bg-amber-900 dark:text-amber-300 dark:border-amber-700': this.bucket === 'warning',
                    };
                },
                autoSubmit() {
                    if (this.autoSubmitting) {
                        return;
                    }

                    this.autoSubmitting = true;
                    this.submitting = true;
                    this.detachBeforeUnload();
                    this.display = '00:00';
                    clearInterval(this.timerId);

                    window.axios.post(submitUrl).finally(() => {
                        window.location.href = submittedUrl;
                    });
                },
            };
        }
    </script>
</x-app-layout>
