<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The app's single semester vocabulary (SEM-01, SEM-02, SEM-03) — a derived
 * value object, not a table. Mirrors the "computed, not stored" discipline
 * of App\Models\Section::windowStatus() and App\Models\Exam::availabilityState():
 * reads `Section.year` / `Section.semester` as the source of truth for the
 * (year, number) pair this class wraps, but does NOT import or couple to
 * that model — every consumer beyond this class's own tests is out of scope
 * for phase 09 (Section -> Semester wiring belongs to Phase 11).
 *
 * Locked semantics:
 * - Semester(Y, 1) = "1st semester of Y" = 1 Sep Y through the last day of
 *   Feb Y+1 (S1 crosses a calendar-year boundary).
 * - Semester(Y, 2) = "2nd semester of Y" = 1 Mar Y through 31 Jul Y.
 * - Therefore S2-Y precedes S1-Y chronologically (Mar Y is before Sep Y).
 *   The academic-year reading where S1-Y is followed by S2-(Y+1) is NOT
 *   this model. Settled by SEM-01's wording — "of the following year"
 *   qualifies only S1's February, while S2 is "March -> July" unqualified —
 *   and by D-04, which fixes Section.year/Section.semester as the source of
 *   truth for the (year, number) pair.
 * - August (1-31) falls in NO semester's date range, but forDate() still
 *   returns one (D-01 roll-forward). This is a deliberate asymmetry:
 *   forDate() is a TOTAL function answering "which semester is current for
 *   this date"; contains() is a RANGE PREDICATE and is false for every
 *   August date on both the previous and the upcoming semester.
 */
final class Semester
{
    public function __construct(
        public readonly int $year,
        public readonly int $number,
    ) {
        if ($number !== 1 && $number !== 2) {
            throw new \InvalidArgumentException("Semester number must be 1 or 2, got {$number}.");
        }
    }

    /**
     * Total function: every date resolves to exactly one semester,
     * including the Sep -> Feb rollover and the August gap (D-01: August
     * rolls FORWARD to the upcoming S1 — a null would blank every
     * dashboard for a month). Branch order is load-bearing; do not reorder.
     */
    public static function forDate(CarbonInterface $date): self
    {
        $month = (int) $date->format('n');

        return match (true) {
            // Sep-Dec belong to S1 of the same year.
            $month >= 9 => new self($date->year, 1),
            // Jan/Feb belong to the S1 that STARTED last September, hence
            // the -1. Getting this wrong is 09-RESEARCH.md Pitfall 1 and
            // silently corrupts every downstream ordinal() comparison.
            $month <= 2 => new self($date->year - 1, 1),
            // Mar-Jul are S2 of the same year.
            $month <= 7 => new self($date->year, 2),
            // August only (D-01): roll FORWARD to the S1 starting this
            // September, rather than returning null.
            default => new self($date->year, 1),
        };
    }

    public static function current(): self
    {
        return self::forDate(now());
    }

    /**
     * S1 starts 1 Sep of $year; S2 starts 1 Mar of $year.
     */
    public function startsAt(): Carbon
    {
        return $this->number === 1
            ? Carbon::create($this->year, 9, 1)->startOfDay()
            : Carbon::create($this->year, 3, 1)->startOfDay();
    }

    /**
     * S1 ends on the last day of Feb of $year + 1; S2 ends 31 Jul of $year.
     * Uses endOfMonth() rather than a hardcoded day number — a literal 28
     * silently drops Feb 29 in a leap year (09-RESEARCH.md Pitfall 2).
     */
    public function endsAt(): Carbon
    {
        return $this->number === 1
            ? Carbon::create($this->year + 1, 2, 1)->endOfMonth()->endOfDay()
            : Carbon::create($this->year, 7, 31)->endOfDay();
    }

    /**
     * Range predicate — inclusive on both ends. This deliberately diverges
     * from Section::windowStatus()'s half-open [opens_at, closes_at)
     * discipline: endsAt() is already end-of-day on the last day, and the
     * semesters are non-contiguous (S2 ends 31 Jul, S1 starts 1 Sep), so
     * there is no adjacent boundary for a half-open interval to
     * disambiguate. Do not "fix" this to match the sibling predicate.
     */
    public function contains(CarbonInterface $date): bool
    {
        return $date->between($this->startsAt(), $this->endsAt());
    }

    /**
     * A total order that agrees with startsAt() ordering, including across
     * the S1 year rollover: S2-Y -> 2Y, S1-Y -> 2Y+1, S2-(Y+1) -> 2Y+2.
     *
     * CORRECTION to 09-RESEARCH.md: research proposes
     * `year * 2 + (number minus 1)`, which ranks S1-2026 (4052)
     * below S2-2026 (4053) — inverted, since S2-2026 starts Mar 2026 and
     * S1-2026 starts Sep 2026 (S2-2026 is chronologically earlier).
     * Research's proposed test only compared S2-2026 against S1-2027, so
     * the inversion passed unnoticed. Adopting it would break Phase 11's
     * "this and future semesters" dashboard query — the precise SEM-02
     * failure this requirement exists to prevent. Guarded by
     * SemesterTest::test_ordinal_is_monotonic_with_start_date.
     */
    public function ordinal(): int
    {
        return $this->year * 2 + (2 - $this->number);
    }

    public function isCurrent(): bool
    {
        return $this->ordinal() === self::current()->ordinal();
    }

    public function isFuture(): bool
    {
        return $this->ordinal() > self::current()->ordinal();
    }

    public function isPast(): bool
    {
        return $this->ordinal() < self::current()->ordinal();
    }

    public function label(): string
    {
        return "{$this->year} Semester {$this->number}";
    }
}
