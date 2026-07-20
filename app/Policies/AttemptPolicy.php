<?php

namespace App\Policies;

use App\Models\Attempt;
use App\Models\User;

class AttemptPolicy
{
    /**
     * Ownership-only (REQUIREMENTS.md Resolved Design Decision #7, AVL-04).
     * Post-start attempt access is ownership-gated, not visibility-gated —
     * the enrollment/availability check applies at START ONLY, in
     * ExamPolicy::takeable() / AttemptController@store. Do NOT re-couple
     * this to Exam::visibleTo(): a student's own in-progress attempt would
     * start 403'ing on every autosave/submit the instant they withdraw,
     * are rejected, the availability window closes, or the exam is
     * unpublished/un-assigned — silently discarding unsaved work. Mirrors
     * viewResult() below, which established this precedent first.
     * AttemptPolicyTest (the AVL-04 regression suite) is the guard.
     */
    public function view(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }

    /**
     * Ownership-only — see the doc comment on view() above; identical
     * reasoning and identical contract.
     */
    public function update(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }

    /**
     * Ownership-only, independent of Exam::visibleTo() (D-05, 05-RESEARCH.md
     * Pitfall 1). Deliberately NOT derived from view()/update() — a student's
     * own graded result must stay visible even if the exam is later
     * unpublished or reassigned; only "did I take this attempt" matters here.
     */
    public function viewResult(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
