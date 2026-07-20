<?php

namespace Tests\Unit;

use App\Support\Semester;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SEM-01/02/03 executable spec — locks the Semester value object's public
 * API before it exists (Wave 0 RED, phase 09). Mirrors WindowSemanticsTest's
 * style: no RefreshDatabase, plain object construction, $this->travelTo()
 * for boundary instants.
 *
 * Locked semantics this file encodes:
 * - Semester(Y, 1) = "1st semester of Y" = 1 Sep Y through the last day of
 *   Feb Y+1.
 * - Semester(Y, 2) = "2nd semester of Y" = 1 Mar Y through 31 Jul Y.
 * - Within one labelled year, S2 comes BEFORE S1 chronologically (Mar Y
 *   precedes Sep Y) — the academic-year reading (S1-Y then S2-(Y+1)) is
 *   NOT the model here.
 *
 * CORRECTION to 09-RESEARCH.md: its proposed ordinal() formula
 * `year * 2 + (number - 1)` is WRONG — it ranks S1-2026 (4052) below
 * S2-2026 (4053), inverted against startsAt() (S2-2026 starts Mar 2026,
 * S1-2026 starts Sep 2026). The correct relation is
 * `ordinal() = year * 2 + (2 - number)`, but this spec does not assert
 * that arithmetic directly — it asserts the *property* (monotonicity with
 * startsAt()) that makes any formula error impossible to miss.
 */
class SemesterTest extends TestCase
{
    public function test_september_date_resolves_to_s1_of_the_same_year(): void
    {
        $semester = Semester::forDate(Carbon::parse('2026-09-01'));

        $this->assertSame(2026, $semester->year);
        $this->assertSame(1, $semester->number);
    }

    public function test_december_date_resolves_to_s1_of_the_same_year(): void
    {
        $semester = Semester::forDate(Carbon::parse('2026-12-31'));

        $this->assertSame(2026, $semester->year);
        $this->assertSame(1, $semester->number);
    }

    public function test_january_date_resolves_to_s1_of_the_previous_year(): void
    {
        $semester = Semester::forDate(Carbon::parse('2027-01-15'));

        $this->assertSame(2026, $semester->year);
        $this->assertSame(1, $semester->number);
    }

    public function test_february_date_resolves_to_s1_of_the_previous_year(): void
    {
        $semester = Semester::forDate(Carbon::parse('2027-02-28'));

        $this->assertSame(2026, $semester->year);
        $this->assertSame(1, $semester->number);
    }

    public function test_march_date_resolves_to_s2_of_the_same_year(): void
    {
        $semester = Semester::forDate(Carbon::parse('2027-03-01'));

        $this->assertSame(2027, $semester->year);
        $this->assertSame(2, $semester->number);
    }

    public function test_july_date_resolves_to_s2_of_the_same_year(): void
    {
        $semester = Semester::forDate(Carbon::parse('2027-07-31'));

        $this->assertSame(2027, $semester->year);
        $this->assertSame(2, $semester->number);
    }

    public function test_august_rolls_forward_to_the_upcoming_s1(): void
    {
        $semester1 = Semester::forDate(Carbon::parse('2026-08-01'));
        $semester2 = Semester::forDate(Carbon::parse('2026-08-15'));
        $semester3 = Semester::forDate(Carbon::parse('2026-08-31'));

        $this->assertSame(2026, $semester1->year);
        $this->assertSame(1, $semester1->number);
        $this->assertSame(2026, $semester2->year);
        $this->assertSame(1, $semester2->number);
        $this->assertSame(2026, $semester3->year);
        $this->assertSame(1, $semester3->number);
    }

    public function test_s1_starts_on_the_first_of_september(): void
    {
        $semester = new Semester(2026, 1);

        $this->assertSame('2026-09-01 00:00:00', $semester->startsAt()->format('Y-m-d H:i:s'));
    }

    public function test_s1_ends_on_the_last_day_of_february_in_a_non_leap_year(): void
    {
        $semester = new Semester(2026, 1);

        $this->assertSame('2027-02-28', $semester->endsAt()->format('Y-m-d'));
    }

    public function test_s1_ends_on_the_last_day_of_february_in_a_leap_year(): void
    {
        // 2028 is a leap year — this proves endOfMonth() was used rather
        // than a hardcoded day number (09-RESEARCH.md Pitfall 2).
        $semester = new Semester(2027, 1);

        $this->assertSame('2028-02-29', $semester->endsAt()->format('Y-m-d'));
    }

    public function test_s2_starts_on_the_first_of_march_and_ends_on_the_last_of_july(): void
    {
        $semester = new Semester(2026, 2);

        $this->assertSame('2026-03-01', $semester->startsAt()->format('Y-m-d'));
        $this->assertSame('2026-07-31', $semester->endsAt()->format('Y-m-d'));
    }

    public function test_august_is_contained_by_no_semester_even_though_it_rolls_forward(): void
    {
        $august = Carbon::parse('2026-08-15');

        // contains() is a range predicate — August is genuinely outside
        // both S1-2026's and S2-2026's date ranges. forDate() is a total
        // function answering "which semester is current for this date"
        // and rolls August forward to the upcoming S1 (SEM-03); the two
        // methods deliberately disagree here.
        $this->assertFalse((new Semester(2026, 1))->contains($august));
        $this->assertFalse((new Semester(2026, 2))->contains($august));
        $this->assertSame(1, Semester::forDate($august)->number);
    }

    public function test_ordinal_orders_s2_before_s1_within_the_same_labelled_year(): void
    {
        // S2-2026 runs Mar-Jul 2026 while S1-2026 runs Sep 2026-Feb 2027 —
        // S2-2026 is chronologically earlier despite sharing the label year.
        $this->assertLessThan((new Semester(2026, 1))->ordinal(), (new Semester(2026, 2))->ordinal());
    }

    public function test_ordinal_sorts_correctly_across_the_s1_year_rollover(): void
    {
        // SEM-02's stated case.
        $this->assertLessThan((new Semester(2027, 2))->ordinal(), (new Semester(2026, 1))->ordinal());
    }

    public function test_ordinal_is_monotonic_with_start_date(): void
    {
        // The load-bearing test: ties ordinal() to startsAt() permanently.
        // Any formula that disagrees with the actual dates fails here
        // regardless of which specific cases the tests above cover.
        $semesters = [
            new Semester(2027, 1),
            new Semester(2026, 2),
            new Semester(2027, 2),
            new Semester(2026, 1),
        ];

        $byOrdinal = $semesters;
        usort($byOrdinal, fn (Semester $a, Semester $b) => $a->ordinal() <=> $b->ordinal());

        $byStartsAt = $semesters;
        usort($byStartsAt, fn (Semester $a, Semester $b) => $a->startsAt() <=> $b->startsAt());

        $this->assertSame(
            array_map(fn (Semester $s) => $s->label(), $byStartsAt),
            array_map(fn (Semester $s) => $s->label(), $byOrdinal),
        );
    }

    public function test_is_current_reflects_the_travelled_clock(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01'));

        $this->assertTrue((new Semester(2026, 1))->isCurrent());
        $this->assertTrue((new Semester(2026, 2))->isPast());
        $this->assertTrue((new Semester(2027, 2))->isFuture());
    }

    public function test_a_semester_number_outside_one_or_two_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Semester(2026, 3);
    }
}
