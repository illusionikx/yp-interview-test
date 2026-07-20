{{--
    Shared MCQ/Open question-authoring form (D-07/Pattern 1), reused for
    both create (exams/show.blade.php) and edit (questions/edit.blade.php).

    Pass an optional $question (with its `options` relation loaded) to
    render in edit mode — pre-fills type/body/points/options from the
    model and posts to the update route instead of store.

    Alpine renders dynamic option rows client-side and enforces "at most
    one correct" via a single shared-name `correct_option` radio group —
    this is UX only; Store/UpdateQuestionRequest re-verify everything
    server-side (T-02-MCQ). The options block is only submitted at all
    when type=mcq, since `options`/`correct_option` are validated
    whenever present regardless of type.
--}}
@php
    $question = $question ?? null;
    $isEdit = $question !== null;
    $attemptCounts = $attemptCounts ?? ['total' => 0, 'notYetGraded' => 0, 'graded' => 0];
    // 12-REVIEW WR-01: a unique save-warning modal name per form instance.
    // The Details form, the Add-question form, and every per-question Edit
    // form previously all rendered the modal under the same window-scoped
    // name 'save-exam-changes', so triggering one opened all of them.
    $saveModalName = $isEdit ? 'save-exam-changes-q'.$question->id : 'save-exam-changes-new';
    // Unique field-id suffix: every question now renders this form at once
    // (all-edit-mode), so bare id="body"/"type"/"points" would collide across
    // questions and misassociate every <label for=...>. Suffix per question.
    $fid = $isEdit ? $question->id : 'new';
@endphp
<div
    x-data="{
        type: '{{ old('type', $question->type?->value ?? 'mcq') }}',
        options: {{ Illuminate\Support\Js::from(
            old('options')
                ? collect(old('options'))->values()->map(fn ($option, $index) => ['key' => $index, 'body' => $option['body'] ?? ''])->all()
                : ($question?->options->isNotEmpty()
                    ? $question->options->values()->map(fn ($option, $index) => ['key' => $index, 'body' => $option->body])->all()
                    : [['key' => 0, 'body' => ''], ['key' => 1, 'body' => '']])
        ) }},
        correct: {{ (int) old('correct_option', $question?->options->search(fn ($option) => $option->is_correct) ?: 0) }},
        nextKey: {{ old('options') ? count(old('options')) : ($question?->options->count() ?: 2) }},
        // Only reveal this question's Save button once it's actually been
        // edited — so the editor isn't a wall of Save buttons across every
        // question. Any field change, or any option mutation, flips it.
        dirty: {{ $errors->any() && ! $isEdit ? 'true' : 'false' }},
        // Whether this exam has attempts — drives the confirm-vs-save branch.
        // When true, Save opens the warn-and-void modal (native submit, redirect);
        // when false, Save posts via axios and the page never reloads.
        hasAttempts: {{ $attemptCounts['total'] > 0 ? 'true' : 'false' }},
        // The per-form modal name to open when attempts exist.
        saveModal: '{{ $saveModalName }}',
        // Drives the transient 'Saved' indicator after a successful ajax save.
        saved: false,
        // True only while an intentional navigation/submit is in flight, so the
        // beforeunload guard below stays quiet on Save/Discard (not on link-clicks).
        submitting: false,
        // Flat field->first-message map populated on an ajax 422.
        errors: {},
        onSubmit(form) {
            // Attempted exam: keep the existing confirm-modal → native-submit
            // (warn-and-void) flow byte-for-byte. No-attempt exam: ajax save.
            if (this.hasAttempts) {
                this.$dispatch('open-modal', this.saveModal);
                return;
            }
            this.saveViaAjax(form);
        },
        async saveViaAjax(form) {
            this.errors = {};
            try {
                // FormData carries the CSRF _token and PUT-spoof _method
                // fields exactly as the native form; window.axios adds
                // X-Requested-With automatically, hitting the 204 branch.
                await window.axios.post(form.action, new FormData(form));
                this.dirty = false;
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 2000);
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    const map = {};
                    Object.entries(error.response.data.errors || {}).forEach(([key, messages]) => {
                        // Collapse options.*.body / correct_option-adjacent
                        // option errors into a single 'options' entry.
                        const field = key.startsWith('options') ? 'options' : key;
                        if (! map[field]) {
                            map[field] = messages[0];
                        }
                    });
                    // Leave dirty true so the Save row stays visible to fix + resave.
                    this.errors = map;
                    return;
                }
                // Any other error: fall back to a native round-trip.
                this.submitting = true;
                form.submit();
            }
        },
        addOption() {
            this.options.push({ key: this.nextKey++, body: '' });
            this.dirty = true;
        },
        removeOption(index) {
            this.options.splice(index, 1);
            if (this.correct >= this.options.length) {
                this.correct = this.options.length - 1;
            }
            this.dirty = true;
        },
        moveOption(index, delta) {
            const target = index + delta;
            if (target < 0 || target >= this.options.length) {
                return;
            }
            const [moved] = this.options.splice(index, 1);
            this.options.splice(target, 0, moved);
            // Keep `correct` pointing at the same option after the swap.
            if (this.correct === index) {
                this.correct = target;
            } else if (this.correct === target) {
                this.correct = index;
            }
            this.dirty = true;
        },
        shuffleOptions() {
            // Client-side authoring shuffle — reorders the option array so the
            // new order is what gets persisted on submit (the server assigns
            // position by array index), keeping `correct` on the same option.
            const correctKey = this.options[this.correct]?.key;
            const shuffled = [...this.options];
            for (let i = shuffled.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
            }
            this.options = shuffled;
            this.correct = Math.max(0, shuffled.findIndex((o) => o.key === correctKey));
            this.dirty = true;
        },
     }"
    x-on:beforeunload.window="if (dirty && ! submitting) { $event.preventDefault(); $event.returnValue = ''; }"
>
    <form
        method="POST"
        action="{{ $isEdit ? route('lecturer.exams.questions.update', [$exam, $question]) : route('lecturer.exams.questions.store', $exam) }}"
        x-on:input="dirty = true"
        x-on:change="dirty = true"
        x-ref="questionForm"
        @submit.prevent="onSubmit($el)"
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div>
            <x-input-label for="type-{{ $fid }}" :value="__('Question type')" class="dark:text-gray-300" />
            <select id="type-{{ $fid }}" name="type" x-model="type" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm">
                <option value="mcq">{{ __('Multiple choice') }}</option>
                <option value="open">{{ __('Open text') }}</option>
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="body-{{ $fid }}" :value="__('Question text')" class="dark:text-gray-300" />
            <textarea id="body-{{ $fid }}" name="body" rows="2" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" required>{{ old('body', $question->body ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('body')" class="mt-2" />
        </div>

        <div class="mt-4 max-w-xs">
            <x-input-label for="points-{{ $fid }}" :value="__('Points')" class="dark:text-gray-300" />
            <x-text-input id="points-{{ $fid }}" name="points" type="number" min="1" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white" :value="old('points', $question->points ?? 1)" />
            <x-input-error :messages="$errors->get('points')" class="mt-2" />
        </div>

        <template x-if="type === 'mcq'">
            <div class="mt-4">
                <x-input-label :value="__('Options (select the correct one)')" class="dark:text-gray-300" />
                <x-input-error :messages="$errors->get('correct_option')" class="mt-1" />
                <x-input-error :messages="$errors->get('options')" class="mt-1" />

                <div class="space-y-2.5 mt-2">
                    <template x-for="(option, index) in options" :key="option.key">
                        <div class="group flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors"
                             :class="correct === index ? 'bg-blue-50/60 dark:bg-blue-900/20' : ''">
                            {{-- Answer move up/down (client-side; the new order is saved on submit). --}}
                            <span class="flex flex-col items-center gap-0.5 text-xs">
                                <button type="button" @click="moveOption(index, -1)" :disabled="index === 0" class="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200 disabled:opacity-30 disabled:cursor-not-allowed leading-none" aria-label="{{ __('Move answer up') }}">&#9650;</button>
                                <button type="button" @click="moveOption(index, 1)" :disabled="index === options.length - 1" class="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200 disabled:opacity-30 disabled:cursor-not-allowed leading-none" aria-label="{{ __('Move answer down') }}">&#9660;</button>
                            </span>
                            <input type="radio" name="correct_option" :value="index" x-model.number="correct" title="{{ __('Mark as the correct answer') }}" class="h-4 w-4 border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 cursor-pointer">
                            <input
                                type="text"
                                :name="'options[' + index + '][body]'"
                                x-model="option.body"
                                class="block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
                                :placeholder="__('Option text')"
                            >
                            <button type="button" @click="removeOption(index)" x-show="options.length > 2" class="shrink-0 text-sm text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400" aria-label="{{ __('Remove option') }}">{{ __('Remove') }}</button>
                        </div>
                    </template>
                </div>

                <div class="mt-3 flex items-center gap-4">
                    <button type="button" @click="addOption()" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">{{ __('+ Add option') }}</button>
                    <button type="button" @click="shuffleOptions()" x-show="options.length > 1" class="text-sm text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">{{ __('Shuffle options') }}</button>
                </div>
            </div>
        </template>

        {{-- Inline validation summary for the AJAX (no-reload) 422 path — the
             server-rendered <x-input-error> above cover the no-JS redirect-back
             fallback; the two never populate at once. --}}
        <ul x-show="Object.keys(errors).length > 0" x-cloak class="mt-4 text-sm text-red-600 dark:text-red-400 space-y-1 list-disc list-inside">
            <template x-for="(message, field) in errors" :key="field">
                <li x-text="message"></li>
            </template>
        </ul>

        {{-- Save appears only once this question has unsaved edits (dirty), so
             the editor isn't a wall of Save buttons. "Discard" reloads to drop
             this question's unsaved changes. The outer wrapper is NOT gated on
             dirty so the "Saved" indicator can linger for its 2s window after
             an ajax save flips dirty back to false. --}}
        <div class="flex items-center justify-end mt-6 gap-3">
            <span x-show="saved" x-cloak class="text-sm text-green-600 dark:text-green-400">{{ __('Saved') }}</span>
            <div class="flex items-center gap-3" x-show="dirty" x-cloak>
                @if ($isEdit)
                    <button type="button" @click="submitting = true; window.location.reload()" class="text-sm text-gray-600 dark:text-gray-400 underline">{{ __('Discard') }}</button>
                @endif
                <x-primary-button type="submit">{{ $isEdit ? __('Save question') : __('Add question') }}</x-primary-button>
            </div>
        </div>
    </form>

    @include('lecturer.exams._save-warning-modal', ['exam' => $exam, 'attemptCounts' => $attemptCounts, 'formRef' => 'questionForm', 'modalName' => $saveModalName])
</div>
