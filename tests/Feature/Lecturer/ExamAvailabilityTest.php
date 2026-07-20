<?php

namespace Tests\Feature\Lecturer;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED (Phase 8, Wave 0) — AVL-01: a lecturer can set an optional
 * available_from/available_until window on a draft exam, including the
 * flagged Assumption A1 (an unfilled datetime-local input submits as an
 * empty string; whether Laravel's ConvertEmptyStringsToNull middleware
 * coerces it to null before validation was NOT verified against this
 * project — 08-RESEARCH.md). D-06 (published exams are immutable) stands
 * unchanged and must cover the new fields too — no exception carved.
 * Expected RED until StoreExamRequest/UpdateExamRequest gain the
 * availability rules and lecturer/exams/{create,edit}.blade.php gain the
 * input pair (08-06).
 */
class ExamAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(Subject $subject, array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $subject->id,
            'title' => 'Midterm',
            'duration_minutes' => 60,
        ], $overrides);
    }

    public function test_a_lecturer_can_set_both_available_from_and_available_until_on_a_draft_exam(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $from = now()->addDay()->format('Y-m-d\TH:i');
        $until = now()->addDays(8)->format('Y-m-d\TH:i');

        $this->actingAs($lecturer)->post(route('lecturer.exams.store'), $this->payload($subject, [
            'available_from' => $from,
            'available_until' => $until,
        ]));

        $exam = Exam::firstWhere('title', 'Midterm');
        $this->assertNotNull($exam);
        $this->assertNotNull($exam->available_from);
        $this->assertNotNull($exam->available_until);
    }

    public function test_a_lecturer_can_set_only_available_from(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $from = now()->addDay()->format('Y-m-d\TH:i');

        $this->actingAs($lecturer)->post(route('lecturer.exams.store'), $this->payload($subject, [
            'available_from' => $from,
            'available_until' => '',
        ]));

        $exam = Exam::firstWhere('title', 'Midterm');
        $this->assertNotNull($exam);
        $this->assertNotNull($exam->available_from);
        $this->assertNull($exam->available_until);
    }

    public function test_a_lecturer_can_set_only_available_until(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $until = now()->addDays(8)->format('Y-m-d\TH:i');

        $this->actingAs($lecturer)->post(route('lecturer.exams.store'), $this->payload($subject, [
            'available_from' => '',
            'available_until' => $until,
        ]));

        $exam = Exam::firstWhere('title', 'Midterm');
        $this->assertNotNull($exam);
        $this->assertNull($exam->available_from);
        $this->assertNotNull($exam->available_until);
    }

    /**
     * Assumption A1 — submit the fields as literal empty strings (not
     * omitted keys) so the coercion path is genuinely exercised. If this
     * fails once the fields are wired, the fix belongs in 08-06 (a
     * prepareForValidation() normalisation in the Form Requests) — do not
     * paper over it here by omitting the keys.
     */
    public function test_submitting_both_availability_fields_as_blank_strings_persists_null_on_both_with_no_validation_error(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.store'), $this->payload($subject, [
            'available_from' => '',
            'available_until' => '',
        ]));

        $response->assertSessionHasNoErrors();
        $exam = Exam::firstWhere('title', 'Midterm');
        $this->assertNotNull($exam);
        $this->assertNull($exam->available_from);
        $this->assertNull($exam->available_until);
    }

    public function test_available_until_earlier_than_available_from_is_a_422(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $subject = Subject::factory()->create();
        $from = now()->addDays(8)->format('Y-m-d\TH:i');
        $until = now()->addDay()->format('Y-m-d\TH:i');

        $response = $this->actingAs($lecturer)->post(route('lecturer.exams.store'), $this->payload($subject, [
            'available_from' => $from,
            'available_until' => $until,
        ]));

        $response->assertSessionHasErrors('available_until');
        $this->assertSame(0, Exam::where('title', 'Midterm')->count());
    }

    public function test_a_lecturer_can_update_a_draft_exams_window(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();
        $from = now()->addDay()->format('Y-m-d\TH:i');
        $until = now()->addDays(8)->format('Y-m-d\TH:i');

        $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => $exam->title,
            'duration_minutes' => $exam->duration_minutes,
            'available_from' => $from,
            'available_until' => $until,
        ]);

        $exam->refresh();
        $this->assertNotNull($exam->available_from);
        $this->assertNotNull($exam->available_until);
    }

    /**
     * D-06's "no exception carved" rationale is RETIRED by D-4: the
     * draft-only edit gate no longer exists at all, so there is no
     * special case left to carve one out of. Setting the window on a
     * published exam is now allowed, same as every other exam field —
     * this exam has no attempts, so no voiding fires either.
     */
    public function test_setting_the_window_on_a_published_exam_is_now_allowed(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->published()->available()->create();
        // available()'s available_until is now()+7 days; the new
        // available_from must stay before it to satisfy the "after"
        // validation rule on available_until — this test only proves the
        // publish gate is lifted, not that validation is bypassed.
        $newFrom = now()->addDays(2)->format('Y-m-d\TH:i');

        $response = $this->actingAs($lecturer)->put(route('lecturer.exams.update', $exam), [
            'subject_id' => $exam->subject_id,
            'title' => $exam->title,
            'duration_minutes' => $exam->duration_minutes,
            'available_from' => $newFrom,
            'available_until' => $exam->available_until->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('lecturer.exams.show', $exam));
        $exam->refresh();
        $this->assertTrue($exam->available_from->equalTo(Carbon::parse($newFrom)));
    }

    /**
     * EDT-02 (plan 12-02) folded the standalone edit form into the
     * `exams.show` Details tab — `exams.edit` now 302s to `exams.show`,
     * so this asserts against the editor page directly.
     */
    public function test_the_create_form_and_editor_both_render_the_availability_inputs(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $exam = Exam::factory()->create();

        $createResponse = $this->actingAs($lecturer)->get(route('lecturer.exams.create'));
        $showResponse = $this->actingAs($lecturer)->get(route('lecturer.exams.show', $exam));

        $createResponse->assertSee('name="available_from"', false);
        $createResponse->assertSee('name="available_until"', false);
        $showResponse->assertSee('name="available_from"', false);
        $showResponse->assertSee('name="available_until"', false);
    }
}
