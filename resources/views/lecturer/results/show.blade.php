<x-app-layout>
    {{--
        Screen 1 (05-UI-SPEC.md) — lecturer per-attempt drill-in / grading
        screen. Unlike the student result view (D-07), this view MAY reveal
        the correct option's body for a wrong MCQ answer (lecturer-only
        sanity check). The open-text score form is wired in Plan 05-03
        Task 2 once the grade route exists.
    --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Grading') }}: {{ $attempt->user->name }} &mdash; {{ $exam->title }}
        </h2>
    </x-slot>

    @php
        // Trim a decimal-cast numeric string down to its plain integer/
        // decimal form ("3.00" -> "3"), matching the student result view's
        // number formatting convention.
        $formatNumber = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    @endphp

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6"
             x-data="gradePage({
                status: @js($attempt->status),
                graded: {{ $gradedOpenText }},
                totalOpen: {{ $totalOpenText }},
                score: @js($formatNumber($attempt->score)),
             })">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ $attempt->user->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $exam->title }}</p>
                    </div>

                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          :class="status === 'graded' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300'"
                          x-text="status === 'graded' ? @js(__('Graded')) : @js(__('Submitted'))"></span>
                </div>

                <p x-show="status === 'graded'" class="mt-4 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                    <span x-text="score"></span> / {{ $formatNumber($totalPossible) }} {{ __('points') }}
                </p>
                <div x-show="status !== 'graded'" class="mt-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                        {{-- ponytail: fragmented __() — English-only app; the :x/:n
                             template can't hold live Alpine spans. --}}
                        <span x-text="graded"></span> {{ __('of') }} <span x-text="totalOpen"></span> {{ __('open-text answers graded') }}
                    </p>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2" role="progressbar" :aria-valuenow="graded" aria-valuemin="0" :aria-valuemax="totalOpen">
                        <div class="bg-blue-600 dark:bg-blue-500 h-2 rounded-full" :style="`width: ${totalOpen > 0 ? Math.round(graded / totalOpen * 100) : 100}%`"></div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                @foreach ($breakdown as $index => $item)
                    @php
                        $question = $item['question'];
                        $answer = $item['answer'];
                        $correctOption = $item['correct_option'];
                    @endphp
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Question :n of :total · :points :label', [
                                'n' => $index + 1,
                                'total' => $breakdown->count(),
                                'points' => $question->points,
                                'label' => $question->points === 1 ? __('point') : __('points'),
                            ]) }}
                        </p>

                        <p class="mt-1 font-semibold text-gray-800 dark:text-gray-200">{{ $question->body }}</p>

                        @if ($question->type === \App\Enums\QuestionType::Mcq)
                            <p class="mt-3 text-sm text-gray-800 dark:text-gray-200">
                                {{ $answer?->selectedOption?->body ?? __('No answer submitted') }}
                            </p>

                            @if ($answer?->is_correct)
                                <p class="mt-2 text-sm font-medium text-green-700 dark:text-green-400">{{ __('✓ Correct') }}</p>
                            @else
                                <p class="mt-2 text-sm font-medium text-red-700 dark:text-red-400">{{ __('✗ Incorrect') }}</p>
                            @endif

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Auto-graded') }}</p>

                            @if (! $answer?->is_correct && $correctOption)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Correct answer:') }} {{ $correctOption->body }}</p>
                            @endif
                        @else
                            @if ($answer)
                                <div class="mt-3 bg-gray-50 dark:bg-gray-700 rounded-md p-4 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ $answer->answer_text }}</div>
                            @else
                                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 italic">{{ __('No answer submitted') }}</p>
                            @endif

                            @if ($answer)
                                <div class="mt-3" x-data="{ editing: {{ $answer->score === null ? 'true' : 'false' }}, saving: false, shownScore: @js($formatNumber($answer->score)), error: '' }">
                                    <template x-if="!editing">
                                        <div>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                <span x-text="shownScore"></span> / {{ $formatNumber($question->points) }} {{ __('pts') }}
                                            </span>
                                            <button type="button" @click="editing = true" class="ml-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 underline">{{ __('Edit') }}</button>
                                        </div>
                                    </template>

                                    <div x-show="editing">
                                        <form method="POST" action="{{ route('lecturer.attempts.answers.grade', [$attempt, $answer]) }}" class="flex items-start gap-2" @submit.prevent="save($event.target, $data)">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="answer_id" value="{{ $answer->id }}">

                                            <div>
                                                <label for="score-{{ $answer->id }}" class="sr-only">{{ __('Score') }}</label>
                                                <input type="number" id="score-{{ $answer->id }}" name="score"
                                                    min="0" max="{{ $question->points }}" step="1"
                                                    value="{{ old('answer_id') == $answer->id ? old('score') : $answer->score }}"
                                                    class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">

                                                {{-- Scoped to this answer's own form: a shared 'score' field name
                                                     across N per-answer forms must not bleed one form's error onto
                                                     every other answer's slot (05-UI-SPEC.md Interaction rule 4).
                                                     Server-side @error is the no-JS fallback; the AJAX path shows
                                                     the same message via `error` below. --}}
                                                @if (old('answer_id') == $answer->id)
                                                    <x-input-error :messages="$errors->get('score')" class="mt-1" />
                                                @endif
                                                <p x-show="error" x-text="error" x-cloak class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                                            </div>

                                            <button type="submit" x-bind:disabled="saving" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                                <span x-show="!saving">{{ __('Save Score') }}</span>
                                                <span x-show="saving" x-cloak>{{ __('Saving…') }}</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>

            <a href="{{ route('lecturer.exams.show', $exam) }}" class="text-sm text-gray-600 dark:text-gray-400 underline">{{ __('Back to exam') }}</a>
        </div>
    </div>

    <script>
        // Grading page: save a score via fetch and patch the row + header in
        // place, so saving no longer reloads the whole page. `item` is the
        // per-answer Alpine scope ($data) whose editing/shownScore/error it
        // flips; `this` is the header state (status/graded/totalOpen/score).
        function gradePage(initial) {
            return {
                status: initial.status,
                graded: initial.graded,
                totalOpen: initial.totalOpen,
                score: initial.score,
                // `save` is called from each child form's scope, so a plain
                // method's `this` would bind to the child, not the header.
                // Capture the header component here and use an arrow closure.
                init() {
                    const header = this;
                    this.save = async (form, item) => {
                        item.error = '';
                        item.saving = true;
                        try {
                            const res = await fetch(form.action, {
                                method: 'POST', // Laravel spoofs PATCH via the _method field
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                body: new FormData(form),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.status === 422) {
                                item.error = data.errors?.score?.[0] ?? @js(__('Invalid score.'));
                                return;
                            }
                            if (!res.ok) {
                                item.error = data.message ?? @js(__('Could not save. Please try again.'));
                                return;
                            }
                            item.shownScore = data.answerScore;
                            item.editing = false;
                            header.status = data.status;
                            header.graded = data.gradedOpenText;
                            header.totalOpen = data.totalOpenText;
                            header.score = data.score;
                        } catch (e) {
                            item.error = @js(__('Could not save. Please try again.'));
                        } finally {
                            item.saving = false;
                        }
                    };
                },
            };
        }
    </script>
</x-app-layout>
