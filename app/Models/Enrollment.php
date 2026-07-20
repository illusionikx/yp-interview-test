<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\RejectionReason;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * SECURITY (T-08-01-MA): Enrollment extends Illuminate's Pivot, which sets
 * $guarded = [] — every column on this model, including status and
 * rejection_reason, is mass-assignable. Writes to this model must always
 * pass literal, explicitly-keyed arrays (e.g. ['status' => ..., ...]) and
 * must NEVER forward raw request input (e.g. $request->all()) into
 * create()/update()/fill(). This invariant is what 08-04/08-05's writes
 * depend on to keep a student from spoofing their own enrollment status.
 */
class Enrollment extends Pivot
{
    protected $table = 'enrollments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'rejection_reason' => RejectionReason::class,
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
