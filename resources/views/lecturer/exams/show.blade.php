<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                {{ $exam->title }}
            </h2>
            {{-- EDT-02: the standalone exams.edit page is gone — the whole page below
                 IS the editor, so this leads back out to the subject's Exams tab. --}}
            <x-back-button :href="route('lecturer.subjects.manage', ['subject' => $exam->subject_id]) . '?tab=exams'">{{ __('Back to exams') }}</x-back-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <div class="flex items-start justify-between">
                    <p>
                        <x-status-pill :status="$exam->is_published ? 'published' : 'draft'">
                            {{ $exam->is_published ? __('Published') : __('Draft') }}
                        </x-status-pill>
                    </p>

                    <div class="flex items-center gap-3">
                        {{-- Sole entry point into the results area (D-06) — visible
                             regardless of publish state, since a lecturer reviews
                             results after students have submitted, which may be
                             after unpublish. --}}
                        <a href="{{ route('lecturer.results.index', $exam) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 text-sm">{{ __('View Results') }}</a>
                        {{-- Always visible regardless of publish state (D-4/EDT-04) — plan 08
                             relaxed the published-edit gate. The whole-exam Delete affordance
                             below stays draft-only; ExamController::destroy() still 403s on a
                             published exam. --}}
                        @if ($exam->is_published)
                            <form method="POST" action="{{ route('lecturer.exams.unpublish', $exam) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-300 dark:focus:ring-blue-800">
                                    {{ __('Unpublish') }}
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('lecturer.exams.publish', $exam) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    {{ __('Publish') }}
                                </button>
                            </form>
                            <div x-data class="inline">
                                <form method="POST" action="{{ route('lecturer.exams.destroy', $exam) }}" x-ref="deleteExamForm" @submit.prevent="$dispatch('open-modal', 'delete-exam')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400 text-sm">{{ __('Delete') }}</button>
                                </form>

                                <x-confirm-modal
                                    name="delete-exam"
                                    :title="__('Delete exam?')"
                                    :body="__('This permanently removes “:name” and all its questions. This cannot be undone.', ['name' => $exam->title])"
                                    confirm-label="Delete"
                                    x-on:click="$refs.deleteExamForm.submit()"
                                />
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- EDT-02: the two-tab editor. Deep-linkable via ?tab=questions
                 (defaults to details), mirroring the CLS-01 hub's tab pattern. --}}
            <div x-data="{ tab: '{{ request('tab', 'details') }}' }">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-6">
                        <button
                            type="button"
                            @click="tab = 'details'"
                            :class="tab === 'details' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold"
                        >
                            {{ __('Details') }}
                        </button>
                        <button
                            type="button"
                            @click="tab = 'questions'"
                            :class="tab === 'questions' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                            class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold"
                        >
                            {{ __('Questions') }}
                        </button>
                    </nav>
                </div>

                {{-- Details tab: exam-details form (EDT-01's name field lives here)
                     moved verbatim from the retired edit.blade.php, plus the CLS-07
                     Submissions panel. --}}
                <div x-show="tab === 'details'" class="mt-6 space-y-6">
                    <div x-data class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                        <form
                            method="POST"
                            action="{{ route('lecturer.exams.update', $exam) }}"
                            @if ($attemptCounts['total'] > 0)
                                x-ref="editExamForm"
                                @submit.prevent="$dispatch('open-modal', 'save-exam-changes')"
                            @endif
                        >
                            @csrf
                            @method('PUT')

                            {{-- The subject is fixed at creation (from the subject's page) and
                                 is not editable here — the server ignores any subject_id on
                                 update, so this is read-only display only. --}}
                            <div>
                                <x-input-label :value="__('Subject')" class="dark:text-gray-300" />
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $exam->subject->name }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Set when the exam was created — it cannot be changed here.') }}</p>
                            </div>

                            <div class="mt-4">
                                <x-input-label for="title" :value="__('Exam / test name')" class="dark:text-gray-300" />
                                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('title', $exam->title)" required autofocus />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <div class="mt-4">
                                <x-input-label for="description" :value="__('Description (optional)')" class="dark:text-gray-300" />
                                <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500 rounded-md shadow-sm">{{ old('description', $exam->description) }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <div class="mt-4">
                                <x-input-label for="duration_minutes" :value="__('Duration (minutes)')" class="dark:text-gray-300" />
                                <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="1" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('duration_minutes', $exam->duration_minutes)" required />
                                <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div>
                                    <x-input-label for="available_from" :value="__('Available from (optional)')" class="dark:text-gray-300" />
                                    <x-text-input id="available_from" name="available_from" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('available_from', $exam->available_from?->format('Y-m-d\TH:i'))" />
                                    <x-input-error :messages="$errors->get('available_from')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="available_until" :value="__('Available until (optional)')" class="dark:text-gray-300" />
                                    <x-text-input id="available_until" name="available_until" type="datetime-local" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('available_until', $exam->available_until?->format('Y-m-d\TH:i'))" />
                                    <x-input-error :messages="$errors->get('available_until')" class="mt-2" />
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Leave blank for no restriction on that side.') }}</p>

                            <div class="flex items-center justify-end mt-6">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    {{ __('Save changes') }}
                                </button>
                            </div>
                        </form>

                        @include('lecturer.exams._save-warning-modal', ['exam' => $exam, 'attemptCounts' => $attemptCounts, 'formRef' => 'editExamForm'])
                    </div>

                    {{-- CLS-07 / INT-02 — the Submissions panel. $attemptCounts is computed once by
                         ExamController::show() (AttemptVoider::summarize()) so this summary line and
                         the confirm-modal body below can never drift out of sync. --}}
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">{{ __('Submissions') }}</h3>

                        @if ($attemptCounts['total'] === 0)
                            <p class="text-sm text-body">{{ __('No students have started this exam yet.') }}</p>

                            <button type="button" disabled class="mt-4 inline-flex items-center px-4 py-2 border border-red-300 text-red-700 dark:border-red-800 dark:text-red-400 text-sm font-semibold rounded-lg opacity-50 cursor-not-allowed">
                                {{ __('Reset submissions') }}
                            </button>
                        @else
                            <p class="text-sm text-body">
                                @if ($attemptCounts['graded'] === 0)
                                    {{ __(':count student(s) have started this exam but have not been graded.', ['count' => $attemptCounts['notYetGraded']]) }}
                                @else
                                    {{ __(':count student(s) have started this exam but have not been graded, and', ['count' => $attemptCounts['notYetGraded']]) }}
                                    <span class="text-red-700 dark:text-red-400">{{ __(':count have already been graded.', ['count' => $attemptCounts['graded']]) }}</span>
                                @endif
                            </p>

                            @php
                                $resetBody = $attemptCounts['graded'] === 0 ? __(':notYetGraded student(s) have started this exam but have not been graded. Resetting will permanently delete :total attempt(s) so they can start again. This cannot be undone.', ['notYetGraded' => $attemptCounts['notYetGraded'], 'total' => $attemptCounts['total']]) : __(':notYetGraded student(s) have started this exam but have not been graded, and :graded student(s) have already been graded. Resetting will permanently delete all :total attempts — including the :graded graded score(s). This cannot be undone.', ['notYetGraded' => $attemptCounts['notYetGraded'], 'graded' => $attemptCounts['graded'], 'total' => $attemptCounts['total']]);
                            @endphp

                            <div x-data class="inline">
                                <form method="POST" action="{{ route('lecturer.exams.submissions.reset', $exam) }}" x-ref="resetSubmissionsForm" @submit.prevent="$dispatch('open-modal', 'reset-submissions')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="mt-4 inline-flex items-center px-4 py-2 border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950/30 text-sm font-semibold rounded-lg">
                                        {{ __('Reset submissions') }}
                                    </button>
                                </form>

                                <x-confirm-modal
                                    name="reset-submissions"
                                    :title="__('Reset exam submissions?')"
                                    :body="$resetBody"
                                    :confirm-label="__('Reset :count submissions', ['count' => $attemptCounts['total']])"
                                    x-on:click="$refs.resetSubmissionsForm.submit()"
                                />
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Questions tab: position-ordered list with inline edit (replacing the
                     retired questions/edit.blade.php page), inline delete, and add-question. --}}
                <div x-show="tab === 'questions'" x-cloak class="mt-6 space-y-6">
                    {{-- EDT-05 (issue #2): reorder happens in-place via optimistic DOM moves
                         + a background PATCH — no full-page refresh. The server routes are
                         unchanged (they still redirect for a no-JS submit); questionReorder()
                         just intercepts the submit, moves the node, persists, and reverts on
                         failure. --}}
                    <div x-data="questionReorder()" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">{{ __('Questions') }}</h3>

                        {{-- D-6 — question deletion also destroys every attempt once any exist, so its
                             confirm-modal copy is conditional on the same $attemptCounts the save-warning
                             modal and the Submissions panel read. Built once here (per-exam, not
                             per-question) since the counts don't vary per loop iteration. --}}
                        @php
                            $questionDeleteTitle = $attemptCounts['total'] > 0
                                ? __('Delete question and reset attempts?')
                                : __('Delete question?');
                            $questionDeleteBody = $attemptCounts['total'] === 0
                                ? __('This permanently removes this question and its options. This cannot be undone.')
                                : ($attemptCounts['graded'] === 0
                                    ? __('This permanently removes this question and its options. :notYetGraded student(s) have started this exam but have not been graded. Deleting will also permanently delete their :total attempt(s) so they can start over. This cannot be undone.', ['notYetGraded' => $attemptCounts['notYetGraded'], 'total' => $attemptCounts['total']])
                                    : __('This permanently removes this question and its options. :notYetGraded student(s) have started this exam but have not been graded, and :graded student(s) have already been graded. Deleting will also permanently delete all :total attempts — including the :graded graded score(s). This cannot be undone.', ['notYetGraded' => $attemptCounts['notYetGraded'], 'graded' => $attemptCounts['graded'], 'total' => $attemptCounts['total']]));
                            $questionDeleteConfirmLabel = $attemptCounts['total'] > 0
                                ? __('Delete & reset :count attempt(s)', ['count' => $attemptCounts['total']])
                                : __('Delete');
                        @endphp

                        {{-- All questions are always open in edit mode — no per-question Edit
                             toggle. Each renders the shared authoring form (_form) with a compact
                             header carrying reorder (up/down, in-place via questionReorder()) and
                             delete. The id lets the one-click "Add question" below scroll here. --}}
                        @forelse ($exam->questions as $question)
                            {{-- Two-column layout (consistent with the take-exam page): the
                                 question number + reorder controls sit in a fixed left gutter,
                                 the editable content fills the right column. --}}
                            <div data-question-row id="question-{{ $question->id }}" class="py-4 border-b border-gray-100 dark:border-gray-700 last:border-b-0 scroll-mt-24 flex gap-4">
                                <div class="flex flex-col items-center gap-1 pt-1 w-10 shrink-0">
                                    <form method="POST" action="{{ route('lecturer.exams.questions.move', [$exam, $question]) }}" @submit.prevent="reorder($el, 'question')">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" data-move="up" @disabled($loop->first) class="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200 disabled:opacity-30 disabled:cursor-not-allowed leading-none" aria-label="{{ __('Move question up') }}">&#9650;</button>
                                    </form>
                                    <span data-q-number class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Q:number', ['number' => $loop->iteration]) }}</span>
                                    <form method="POST" action="{{ route('lecturer.exams.questions.move', [$exam, $question]) }}" @submit.prevent="reorder($el, 'question')">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" data-move="down" @disabled($loop->last) class="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200 disabled:opacity-30 disabled:cursor-not-allowed leading-none" aria-label="{{ __('Move question down') }}">&#9660;</button>
                                    </form>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-end mb-2">
                                        <div x-data class="inline">
                                            <form method="POST" action="{{ route('lecturer.exams.questions.destroy', [$exam, $question]) }}" x-ref="deleteQuestionForm" @submit.prevent="$dispatch('open-modal', 'delete-question-{{ $question->id }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400 text-sm">{{ __('Delete question') }}</button>
                                            </form>

                                            <x-confirm-modal
                                                name="delete-question-{{ $question->id }}"
                                                :title="$questionDeleteTitle"
                                                :body="$questionDeleteBody"
                                                :confirm-label="$questionDeleteConfirmLabel"
                                                x-on:click="$refs.deleteQuestionForm.submit()"
                                            />
                                        </div>
                                    </div>

                                    @include('lecturer.exams.questions._form', ['exam' => $exam, 'question' => $question, 'attemptCounts' => $attemptCounts])
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No questions yet.') }}</p>
                        @endforelse
                    </div>

                    {{-- One-click add (issue #3): creates a blank question at the end and jumps
                         to it, where it is immediately editable above. Adding a question to an
                         attempted exam voids attempts (D-6), so when attempts exist this goes
                         through the same save-warning modal as every other mutation. --}}
                    <div x-data>
                        <form method="POST" action="{{ route('lecturer.exams.questions.quick', $exam) }}"
                            @if ($attemptCounts['total'] > 0)
                                x-ref="addQuestionForm"
                                @submit.prevent="$dispatch('open-modal', 'add-question-warning')"
                            @endif
                        >
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                {{ __('Add question') }}
                            </button>
                        </form>

                        @include('lecturer.exams._save-warning-modal', ['exam' => $exam, 'attemptCounts' => $attemptCounts, 'formRef' => 'addQuestionForm', 'modalName' => 'add-question-warning'])
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Issue #2: reorder questions/options in place, no page refresh.
        // Optimistically moves the existing DOM node (Alpine state on the node
        // is preserved because the node is MOVED, not re-rendered), persists via
        // a background PATCH, and reverts + reloads on failure. The move buttons
        // are disabled at the boundaries, so a submit only fires when a sibling
        // exists; reindex() then re-derives the Q-numbers and boundary-disabled
        // states client-side to match the new order.
        function questionReorder() {
            return {
                async reorder(form, level) {
                    const selector = level === 'question' ? '[data-question-row]' : '[data-option-row]';
                    const direction = form.querySelector('input[name="direction"]').value;
                    const row = form.closest(selector);
                    const list = row.parentElement;
                    const sibling = direction === 'up'
                        ? row.previousElementSibling
                        : row.nextElementSibling;

                    // Guard: nothing to swap with (boundary, or a non-row sibling
                    // like the section heading sitting above the first question).
                    if (! sibling || ! sibling.matches(selector)) {
                        return;
                    }

                    // Optimistic move.
                    if (direction === 'up') {
                        list.insertBefore(row, sibling);
                    } else {
                        list.insertBefore(sibling, row);
                    }
                    this.reindex(list, selector, level);

                    try {
                        await window.axios.post(form.action, new FormData(form));
                    } catch (error) {
                        // Persist failed — undo the optimistic move and reload so the
                        // page reflects the true server order.
                        if (direction === 'up') {
                            list.insertBefore(sibling, row);
                        } else {
                            list.insertBefore(row, sibling);
                        }
                        this.reindex(list, selector, level);
                        window.location.reload();
                    }
                },
                reindex(list, selector, level) {
                    const rows = Array.from(list.querySelectorAll(':scope > ' + selector));
                    rows.forEach((row, index) => {
                        if (level === 'question') {
                            const number = row.querySelector('[data-q-number]');
                            if (number) {
                                number.textContent = 'Q' + (index + 1);
                            }
                        }
                        const up = row.querySelector('[data-move="up"]');
                        const down = row.querySelector('[data-move="down"]');
                        if (up) up.disabled = index === 0;
                        if (down) down.disabled = index === rows.length - 1;
                    });
                },
            };
        }

        // Issue #3: after a one-click add, the redirect carries #question-<id>.
        // Scroll to it once Alpine has revealed the Questions tab (a bare native
        // anchor jump would miss, since the tab is display:none until Alpine
        // initialises), and focus its question-text field so it's ready to edit.
        document.addEventListener('DOMContentLoaded', () => {
            if (! window.location.hash) {
                return;
            }

            let target;
            try {
                target = document.querySelector(window.location.hash);
            } catch (e) {
                return;
            }
            if (! target) {
                return;
            }

            setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                const body = target.querySelector('textarea[name="body"]');
                if (body) {
                    body.focus();
                }
            }, 100);
        });
    </script>
</x-app-layout>
