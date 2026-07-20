<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    /**
     * Can this student open/take this exam (D-03/D-04, RBAC-05, ENR-08)?
     *
     * Reuses Exam::visibleTo() — the identical predicate the student
     * index uses — so a student can never reach an exam via a direct
     * URL that the index would have hidden from them. Do NOT re-derive
     * is_published/enrollment conditions here; delegate entirely to
     * the shared scope.
     */
    public function takeable(User $user, Exam $exam): bool
    {
        return $user->isStudent()
            && Exam::visibleTo($user)->whereKey($exam->id)->exists();
    }
}
