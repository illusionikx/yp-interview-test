<?php

namespace App\Enums;

/**
 * Fixed, server-enumerable set of enrollment rejection reasons
 * (REQUIREMENTS.md "Resolved Design Decisions (v2.0)" #1 — the sole
 * authority for these five values and labels). Do not "fix" these back
 * toward the superseded candidate set still visible in 08-RESEARCH.md
 * Pattern 8 / 08-PATTERNS.md — that set was explicitly rejected in favour
 * of this one.
 */
enum RejectionReason: string
{
    case NotEligibleForSubject = 'not_eligible_for_subject';
    case PrerequisiteNotMet = 'prerequisite_not_met';
    case DuplicateEnrollment = 'duplicate_enrollment';
    case SectionChanged = 'section_changed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NotEligibleForSubject => 'Not eligible for subject',
            self::PrerequisiteNotMet => 'Prerequisite not met',
            self::DuplicateEnrollment => 'Duplicate enrollment',
            self::SectionChanged => 'Section changed',
            self::Other => 'Other',
        };
    }
}
