<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Lecturer\GradeAnswerRequest;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * INT-01 executable spec — locks the null-guard behavior for a hard-deleted
 * attempt row across THREE independent crash sites (Site 3 added Phase 10,
 * Wave 0 RED — the guard for it does not exist yet, plan 04 adds it):
 *
 * - Site 1: Attempt::lockAndFinalize() (app/Models/Attempt.php:141),
 *   reached via finalize() / finalizeIfExpired(). Today: fatal Error
 *   ("Call to a member function setRelation() on null").
 * - Site 2: AttemptController::answer()'s own separate
 *   Attempt::whereKey(...)->lockForUpdate()->first() call
 *   (app/Http/Controllers/Student/AttemptController.php:172) — NOT a
 *   caller of lockAndFinalize(). Today: fatal Error
 *   ("Call to a member function ->status on null").
 * - Site 3 (Phase 10, D-5, T-09-01): AnswerGradeController::update()
 *   (app/Http/Controllers/Lecturer/AnswerGradeController.php:29) — a
 *   lecturer-facing form PATCH, the first vanished-row call site outside
 *   the student attempt flow. D-2's hard delete (exam reset) promotes
 *   this from a narrow race (a student deleting their own account mid
 *   grade-save) to a routine one (a lecturer grading while another
 *   lecturer resets the same exam). Today: TypeError (null passed to
 *   AttemptGrader::syncStatus()'s non-nullable Attempt parameter).
 *   This site ALSO pins a latent bug in the inherited guard: its
 *   existing non-JSON redirect branch sends the user to
 *   `student.exams.index`, which `routes/student.php:10` gates behind
 *   `role:student` — stranding a lecturer on a 403 telling them to
 *   "return to your exam list". Plan 04 must add a
 *   `routeIs('lecturer.*')` branch with lecturer-appropriate copy.
 *
 * The guard shape this spec locks (implemented by plan 09-05, extended by
 * plan 04 for Site 3): a vanished row raises
 * App\Exceptions\AttemptVanishedException, a typed exception distinct from
 * "already finalized by a racing request" — a bare `return false` would
 * make the two indistinguishable at the call site, and 09-UI-SPEC.md
 * requires a distinct user-facing message for the vanished case ("This
 * exam attempt is no longer available. Please return to your exam list.")
 * versus the ordinary submitted/result path.
 */
class AttemptNullGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Attempt, 2: Question, 3: Option}
     */
    private function attemptFixture(int $durationMinutes = 30): array
    {
        $exam = Exam::factory()->published()->create(['duration_minutes' => $durationMinutes]);
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id]);
        $option = $question->options()->first();

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        return [$student, $attempt, $question, $option];
    }

    /**
     * Removes the attempts row via the query builder, NOT $attempt->delete()
     * — the point is to remove the row while the caller's in-memory model
     * instance survives, which is exactly the production race (a concurrent
     * reset commits while this request holds a stale instance).
     */
    private function hardDeleteAttemptRow(Attempt $attempt): void
    {
        DB::table('attempts')->where('id', $attempt->id)->delete();
    }

    /**
     * Registers a Gate::after hook that hard-deletes $attempt's row the
     * first time any ability is checked against it, then always
     * `return $result;` so the authorization decision passes through
     * unchanged (the hook must never mask an authz failure).
     *
     * Why this seam and not a plain pre-request delete: both
     * AnswerRequest::authorize() (ability `update`) and
     * AttemptController::show()'s $this->authorize('view', ...) both
     * run the Gate AFTER route-model binding and BEFORE the locked read.
     * A pre-request delete would make SubstituteBindings throw a 404
     * before the guard is ever reached, and the test would assert
     * nothing. Gate::after removes the row at exactly the point the
     * production race removes it. Matching on any ability (not only
     * `update`) lets one helper serve both the answer() and show() paths.
     */
    private function registerMidRequestDelete(Attempt $attempt): void
    {
        $deleted = false;

        Gate::after(function ($user, $ability, $result, $arguments) use ($attempt, &$deleted) {
            if (! $deleted && ($arguments[0] ?? null) instanceof Attempt && $arguments[0]->id === $attempt->id) {
                $deleted = true;
                $this->hardDeleteAttemptRow($attempt);
            }

            return $result;
        });
    }

    public function test_finalize_throws_a_typed_exception_when_the_attempt_row_has_vanished(): void
    {
        [, $attempt] = $this->attemptFixture();

        $this->hardDeleteAttemptRow($attempt);

        // An Error on a null dereference is the crash INT-01 exists to
        // remove; a typed AttemptVanishedException is the controlled
        // failure that replaces it. Today this throws
        // `Error: Call to a member function setRelation() on null`, a
        // different class than expected below, so this test is RED.
        $this->expectException(\App\Exceptions\AttemptVanishedException::class);

        $attempt->finalize();
    }

    public function test_finalize_if_expired_throws_a_typed_exception_when_the_attempt_row_has_vanished(): void
    {
        [, $attempt] = $this->attemptFixture();

        $this->travelTo($attempt->started_at->copy()->addMinutes(31));
        $this->hardDeleteAttemptRow($attempt);

        // Same crash site as above (lockAndFinalize), reached through
        // finalizeIfExpired() instead — the second entry point.
        $this->expectException(\App\Exceptions\AttemptVanishedException::class);

        $attempt->finalizeIfExpired();
    }

    public function test_a_surviving_attempt_still_finalizes_normally(): void
    {
        [, $attempt] = $this->attemptFixture();

        $this->assertTrue($attempt->finalize());

        // This MCQ-only fixture has no open-text question pending, so the
        // finalize-time grading hook completes the submitted->graded
        // transition in the same call (mirrors AttemptAnswerTest's
        // test_an_expired_attempt_rejects_answer_writes). This is the
        // control: it must be GREEN both before and after plan 09-05,
        // proving the guard is additive and does not alter the happy path.
        $this->assertDatabaseHas('attempts', ['id' => $attempt->id, 'status' => 'graded']);
    }

    public function test_autosave_fails_safely_when_the_attempt_row_vanishes_mid_request(): void
    {
        [$student, $attempt, $question, $option] = $this->attemptFixture();

        $this->registerMidRequestDelete($attempt);

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        // A 500 here is the INT-01 crash; a 422 carrying `vanished` is the
        // required fail-safe. The response keeps the pre-existing
        // `expired` key so the shipped client-side autosave handler keeps
        // working unchanged (09-RESEARCH.md Assumption A2) — `vanished`
        // is the additional discriminator Phase 10 can branch on.
        $response->assertStatus(422);
        $response->assertJson(['vanished' => true]);
        $this->assertDatabaseCount('answers', 0);
    }

    public function test_the_take_page_redirects_with_an_error_when_the_attempt_row_vanishes_mid_request(): void
    {
        [$student, $attempt] = $this->attemptFixture();

        // Travel past the deadline so the page-load's finalizeIfExpired()
        // actually proceeds into lockAndFinalize() (crash site 1) instead
        // of short-circuiting on the not-yet-expired check — otherwise
        // this request would never touch the deleted row at all and the
        // test would prove nothing about the guard.
        $this->travelTo($attempt->started_at->copy()->addMinutes(31));

        $this->registerMidRequestDelete($attempt);

        $response = $this->actingAs($student)->get(route('student.attempts.show', $attempt));

        $response->assertRedirect(route('student.exams.index'));
        $response->assertSessionHas('error', 'This exam attempt is no longer available. Please return to your exam list.');
    }

    /**
     * Site 3's mid-request-delete seam. `GradeAnswerRequest::authorize()`
     * is a plain boolean check — it does NOT call Gate::check()/
     * $this->authorize(), unlike AnswerRequest's
     * $this->user()->can('update', ...) (site 2) or
     * AttemptController::show()'s $this->authorize('view', ...) (site 1's
     * caller). So the registerMidRequestDelete() Gate::after seam above
     * never fires on this route — nothing calls Gate::check() during its
     * lifecycle (verified by direct read of GradeAnswerRequest,
     * AnswerGradeController, and the `role:lecturer` route-group
     * middleware, none of which touch the Gate facade).
     *
     * This reproduces the identical "vanish after route-model binding,
     * before the locked read" timing via an equivalent seam:
     * App::resolving() on the FormRequest class itself. SubstituteBindings
     * (global middleware) has already bound {attempt}/{answer} to Eloquent
     * instances by the time the controller's dependencies are resolved;
     * the container's resolving() callbacks fire immediately after the
     * FormRequest is built, strictly before
     * FormRequest::validateResolved() runs authorize() — so this is the
     * same "after binding, before the locked read" window the Gate::after
     * seam occupies for sites 1/2, just triggered by a different container
     * event since this route has no Gate call to hook into.
     */
    private function registerMidRequestDeleteOnGradeRequest(Attempt $attempt): void
    {
        $deleted = false;

        $this->app->resolving(GradeAnswerRequest::class, function () use ($attempt, &$deleted) {
            if (! $deleted) {
                $deleted = true;
                $this->hardDeleteAttemptRow($attempt);
            }
        });
    }

    /**
     * A gradeable fixture for Site 3: a submitted attempt with one
     * open-text question and its ungraded answer, so
     * GradeAnswerRequest::authorize() (attempt status in
     * ['submitted', 'graded'], answer question type Open) passes and the
     * request reaches AnswerGradeController::update()'s locked read.
     *
     * @return array{0: Attempt, 1: Answer}
     */
    private function gradableAnswerFixture(): array
    {
        $exam = Exam::factory()->published()->create();
        $section = Section::factory()->create(['subject_id' => $exam->subject_id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $question = Question::factory()->open()->create(['exam_id' => $exam->id]);

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $answer = Answer::factory()->for($attempt)->for($question)->openText()->create();

        return [$attempt, $answer];
    }

    public function test_a_vanished_attempt_row_during_a_lecturer_grade_save_does_not_crash(): void
    {
        [$attempt, $answer] = $this->gradableAnswerFixture();
        $lecturer = User::factory()->lecturer()->create();

        $this->registerMidRequestDeleteOnGradeRequest($attempt);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.attempts.answers.grade', [$attempt, $answer]),
            ['score' => 1]
        );

        // Today this is a TypeError (null passed to
        // AttemptGrader::syncStatus()'s non-nullable Attempt parameter),
        // which Laravel's exception handler renders as a 500 — the exact
        // INT-01 crash D-5 exists to close. A redirect is the required
        // fail-safe once plan 04 lands, so this method is RED today.
        $this->assertNotEquals(500, $response->status());
    }

    /**
     * Pins the fix for the latent lecturer-facing bug this plan's
     * <objective> describes: the exception's existing non-JSON branch
     * redirects to `student.exams.index`, which sits behind
     * `role:student` — a lecturer sent there gets a 403 telling them to
     * return to "your exam list", a dead end. Plan 04 must add a
     * `routeIs('lecturer.*')` branch. RED today because the response is a
     * 500 (crash), not a redirect at all.
     */
    public function test_a_vanished_attempt_row_during_a_lecturer_grade_save_redirects_the_lecturer_somewhere_they_can_actually_go(): void
    {
        [$attempt, $answer] = $this->gradableAnswerFixture();
        $lecturer = User::factory()->lecturer()->create();

        $this->registerMidRequestDeleteOnGradeRequest($attempt);

        $response = $this->actingAs($lecturer)->patch(
            route('lecturer.attempts.answers.grade', [$attempt, $answer]),
            ['score' => 1]
        );

        $response->assertRedirect(route('lecturer.exams.index'));
        $response->assertSessionHas('error');
        $this->assertStringNotContainsString('return to your exam list', (string) session('error'));
    }

    /**
     * Regression guard on the branch about to be added: plan 04's
     * lecturer-facing branch on AttemptVanishedException::render() must
     * not regress the student-facing JSON contract Phase 9 shipped and
     * tested at Site 2 (the autosave route). Reuses the existing site-2
     * fixture/seam verbatim. Expected GREEN today (already guarded) and
     * GREEN after plan 04 (must stay unchanged).
     */
    public function test_the_students_vanished_row_message_is_unchanged_for_student_routes(): void
    {
        [$student, $attempt, $question, $option] = $this->attemptFixture();

        $this->registerMidRequestDelete($attempt);

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['expired' => true, 'vanished' => true]);
    }
}
