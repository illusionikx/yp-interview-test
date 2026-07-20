<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\EnrollRequest;
use App\Models\Enrollment;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    /**
     * Sentinel values returned OUT of store()'s locked transaction closure
     * (DB::transaction() returns whatever the closure returns) and mapped
     * to flash copy in the controller body below. Deliberately not
     * abort(409)/a dedicated exception: 08-UI-SPEC.md requires every
     * refusal to render as a red flash banner on the sections page, not an
     * error page.
     */
    private const RESULT_ENROLLED = 'enrolled';

    private const RESULT_FULL = 'full';

    private const RESULT_ALREADY_ACTIVE_ELSEWHERE = 'already_active_elsewhere';

    /**
     * ENR-01..ENR-05: capacity-safe, immediate (no-approval) apply.
     *
     * Mirrors Lecturer\SectionController@store's DB::transaction() +
     * lockForUpdate() idiom (app/Http/Controllers/Lecturer/SectionController.php),
     * applied to a live capacity count instead of a sequence number.
     */
    public function store(EnrollRequest $request, Section $section): RedirectResponse
    {
        // Window check first — cheap rejection, no lock needed. Reuses
        // Section::windowStatus() (08-01) rather than re-deriving the
        // half-open [opens_at, closes_at) comparison a third time.
        $windowStatus = $section->windowStatus();

        if ($windowStatus === 'opens') {
            return back()->with('error', "Enrollment for this section hasn't opened yet.");
        }

        if ($windowStatus === 'closed') {
            return back()->with('error', 'Enrollment for this section is closed.');
        }

        $result = DB::transaction(function () use ($section, $request) {
            // Exclusive row lock — the ENR-02 safety mechanism. A second
            // concurrent apply to the SAME section blocks here until the
            // first transaction commits, so the count read below is
            // guaranteed current when this request finally reads it.
            $locked = Section::whereKey($section->id)->lockForUpdate()->first();

            // CR-01: the ENR-04 "one active enrollment per subject/term"
            // invariant spans every SIBLING section of this (subject, year,
            // semester) — not just the section being applied to. Locking
            // only $locked's own row (above) does not serialize against a
            // concurrent apply to a different section of the same term,
            // since that request locks a different row entirely. Lock every
            // sibling section here, before the cross-section read below, so
            // all concurrent applies within this term serialize against
            // each other and the hasActiveElsewhere check is race-safe.
            Section::where('subject_id', $locked->subject_id)
                ->where('year', $locked->year)
                ->where('semester', $locked->semester)
                ->lockForUpdate()
                ->get();

            $enrolledCount = $locked->enrollments()
                ->wherePivot('status', EnrollmentStatus::Enrolled->value)
                ->count();

            if ($enrolledCount >= $locked->capacity) {
                return self::RESULT_FULL;
            }

            // ENR-04: one active enrollment per subject/semester — checked
            // inside the SAME locked transaction as the capacity count so
            // both invariants are consistent under one lock.
            $hasActiveElsewhere = Enrollment::query()
                ->where('user_id', $request->user()->id)
                ->where('status', EnrollmentStatus::Enrolled)
                ->where('section_id', '!=', $locked->id)
                ->whereHas('section', fn ($query) => $query
                    ->where('subject_id', $locked->subject_id)
                    ->where('year', $locked->year)
                    ->where('semester', $locked->semester))
                ->lockForUpdate()
                ->exists();

            if ($hasActiveElsewhere) {
                return self::RESULT_ALREADY_ACTIVE_ELSEWHERE;
            }

            // updateOrCreate — never create(). The unique(section_id,
            // user_id) row already exists for anyone who previously
            // withdrew or was rejected (ENR-05 re-apply). rejection_reason
            // is reset to null explicitly (not '') so a stale reason can
            // never linger on a freshly-enrolled row. Both keys of the
            // write array are literal — user_id always comes from the
            // authenticated user, never from request input.
            Enrollment::updateOrCreate(
                ['section_id' => $locked->id, 'user_id' => $request->user()->id],
                ['status' => EnrollmentStatus::Enrolled, 'rejection_reason' => null]
            );

            return self::RESULT_ENROLLED;
        });

        return match ($result) {
            self::RESULT_FULL => back()->with('error', 'This section just reached capacity — choose another section.'),
            self::RESULT_ALREADY_ACTIVE_ELSEWHERE => back()->with('error', 'You already have an active enrollment in this subject for this semester. Withdraw first if you want to switch sections.'),
            default => back()->with('status', "You're enrolled in {$section->name}."),
        };
    }

    /**
     * ENR-03: withdraw before close; refused at/after the half-open close
     * boundary. No lock is needed — this is a single-row write by its own
     * owner with no shared invariant. Scoped to auth()->id() only, never a
     * request-supplied user_id.
     *
     * WR-02: the write is scoped to a currently Enrolled row and its
     * affected-row count is checked, so this can no longer (a) flash a
     * false "withdrawn" success for a student who was never enrolled in
     * this section (0 rows affected), or (b) let a student who was
     * already Rejected self-service-flip their own row back to Withdrawn,
     * silently replacing the lecturer's rejection record.
     */
    public function destroy(Section $section): RedirectResponse
    {
        if ($section->windowStatus() === 'closed') {
            return back()->with('error', "You can no longer withdraw — this section's enrollment window has closed.");
        }

        $updated = Enrollment::where('section_id', $section->id)
            ->where('user_id', auth()->id())
            ->where('status', EnrollmentStatus::Enrolled)
            ->update(['status' => EnrollmentStatus::Withdrawn]);

        if (! $updated) {
            return back()->with('error', "You're not currently enrolled in this section.");
        }

        return back()->with('status', "You've withdrawn from {$section->name}.");
    }
}
