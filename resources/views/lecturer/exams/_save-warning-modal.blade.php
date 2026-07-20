{{--
    EDT-04's single source of the "save an attempted exam" warning copy.
    Included by every editor form that can trigger AttemptVoider (the
    exam-details edit form and both question forms — add and edit share
    questions/_form.blade.php), so the three surfaces cannot drift apart on
    the one string that stands between a lecturer and permanent data loss.

    Params:
    - $exam: the Exam being edited (unused directly in the copy today, kept
      for parity with the other confirm-modal call sites and future reuse).
    - $attemptCounts: the array shape AttemptVoider::summarize() returns
      (total/notYetGraded/graded) — the SAME call the "Submissions" panel on
      show.blade.php reads, so this modal's stakes can never mis-state what
      the page already told the lecturer.
    - $formRef: the x-ref name of the <form> to submit when the lecturer
      confirms. The caller's form must live in the same x-data scope as this
      include for $refs to resolve.

    Renders nothing when $attemptCounts['total'] === 0 — no modal exists at
    zero attempts; friction only exists where risk exists.
--}}
@if ($attemptCounts['total'] > 0)
    @php
        $body = $attemptCounts['graded'] === 0
            ? __(':notYetGraded student(s) have started this exam but have not been graded. Saving your changes will cancel their attempt(s) so they can start over. This cannot be undone.', [
                'notYetGraded' => $attemptCounts['notYetGraded'],
            ])
            : __(':notYetGraded student(s) have started this exam but have not been graded, and :graded student(s) have already been graded. Saving your changes will permanently delete all :total attempts — including the :graded graded score(s). This cannot be undone.', [
                'notYetGraded' => $attemptCounts['notYetGraded'],
                'graded' => $attemptCounts['graded'],
                'total' => $attemptCounts['total'],
            ]);
    @endphp

    <x-confirm-modal
        name="{{ $modalName ?? 'save-exam-changes' }}"
        :title="__('Save changes and reset attempts?')"
        :body="$body"
        :confirm-label="__('Save & reset :count attempt(s)', ['count' => $attemptCounts['total']])"
        x-on:click="submitting = true; $refs.{{ $formRef }}.submit()"
    />
@endif
