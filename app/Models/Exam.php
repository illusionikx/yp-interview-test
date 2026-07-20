<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'created_by',
        'title',
        'description',
        'duration_minutes',
        'is_published',
        'available_from',
        'available_until',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Default-ordered by position (12-05, EDT-05) so every consumer —
     * the editor's Questions tab, the reorder controller's swap logic,
     * the student-taking flow — renders/reads questions in the
     * lecturer's authored order without each call site re-specifying
     * orderBy(). Aggregates like ->max('position') ignore this default
     * order, so ExamQuestionController's position-assignment on
     * create/update is unaffected.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /**
     * The single predicate for "is this exam visible to this student"
     * (D-02/D-03, RBAC-05, ENR-08). Consumed by BOTH
     * Student\ExamController@index (the list) and ExamPolicy::takeable()
     * (the pre-start/direct-access gate) — never re-derive is_published/
     * enrollment conditions anywhere else, or the list and the gate can
     * silently diverge (the "hidden but reachable" bug class this phase
     * exists to prevent).
     *
     * NOT consumed by AttemptPolicy (08-03, REQUIREMENTS.md Resolved
     * Design Decision #7, AVL-04): post-start attempt access is
     * ownership-gated, not visibility-gated, so AttemptPolicy::view()/
     * update() deliberately do NOT call this scope.
     *
     * Visibility is SUBJECT-enrollment-driven (Phase 10, D-1/CLS-05): an
     * exam is visible to every student holding an active (Enrolled)
     * enrollment in ANY section of the exam's own subject — there is no
     * per-exam assignment step anymore. The join walks
     * exam -> subject -> subject's sections -> enrollments, always
     * through subject_id, NEVER through a per-exam section list. The
     * `exam_section` pivot and both `BelongsToMany` relations that backed
     * it (`Exam::sections()`, `Section::exams()`) are gone
     * (`2026_07_17_100001_drop_exam_section_table.php`) — this is what
     * makes v2.0's CRITICAL cross-subject leak (T-10-01) structurally
     * unexpressible: there is no longer any way to say "exam X is visible
     * to a class of subject Y" other than exam.subject_id itself. No
     * explicit null-guard is needed here — whereHas naturally matches zero
     * rows for a student with zero enrollments, which is the same "sees
     * nothing" result the old classroom_id null-guard produced.
     *
     * IMPORTANT (AVL-04): availability window conditions (isAvailableNow()/
     * availabilityState() below) must NEVER be folded into this predicate.
     * Doing so would 403 an in-progress attempt the instant the window
     * closes — availability is an additive, separate check, consumed at
     * exactly one call site (the attempt-start gate).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereHas('subject.sections.enrollments', fn (Builder $q) => $q
                ->where('user_id', $user->id)
                ->where('status', EnrollmentStatus::Enrolled)
            );
    }

    /**
     * AVL-01/AVL-03: is this exam within its optional availability window
     * right now? Half-open interval [available_from, available_until) —
     * inclusive lower bound, exclusive upper bound. A null bound is
     * unbounded on that side. This is deliberately additive and lives
     * OUTSIDE scopeVisibleTo() — see the warning on that method above.
     */
    public function isAvailableNow(): bool
    {
        $now = now();

        return ($this->available_from === null || $now->gte($this->available_from))
            && ($this->available_until === null || $now->lt($this->available_until));
    }

    /**
     * The query-side twin of isAvailableNow() — same half-open window
     * [available_from, available_until). Kept as one source of truth so a
     * caller filtering "available right now" in SQL cannot drift from the
     * PHP predicate above. Additive and separate from scopeVisibleTo(), per
     * the AVL-04 warning on that method — apply it as its own where(), never
     * folded into visibility.
     */
    public function scopeAvailableNow(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>', now()));
    }

    /**
     * AVL-01/AVL-03: the human-facing availability state derived from the
     * same half-open window as isAvailableNow(). Returns exactly one of
     * 'opening' | 'closed' | 'available' — these are the exact status-pill
     * keywords the extended x-status-pill match() arms consume. Deliberately
     * additive and separate from scopeVisibleTo() — see the warning above.
     */
    public function availabilityState(): string
    {
        $now = now();

        if ($this->available_from !== null && $now->lt($this->available_from)) {
            return 'opening';
        }
        if ($this->available_until !== null && $now->gte($this->available_until)) {
            return 'closed';
        }

        return 'available';
    }
}
