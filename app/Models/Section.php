<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Section extends Model
{
    /** @use HasFactory<\Database\Factories\SectionFactory> */
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'year',
        'semester',
        'sequence',
        'capacity',
        'location',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The students enrolled in this section, via the enrollments pivot
     * (SEC-01/ENR-08). Uses the custom Enrollment pivot so pivot->status
     * is cast to EnrollmentStatus rather than staying a raw string.
     */
    public function enrollments(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->using(Enrollment::class)
            ->withPivot(['status', 'rejection_reason'])
            ->withTimestamps();
    }

    /**
     * SEC-01: computed, not stored — mirrors the existing "live accessor
     * over denormalized column" precedent in this codebase, avoiding
     * drift between the stored parts and a redundant name column.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->year}-{$this->semester}-{$this->sequence}",
        );
    }

    /**
     * Computed, not stored — the half-open [opens_at, closes_at) window
     * state, extracted from the inline @php block previously duplicated
     * in lecturer/sections/index.blade.php so every consumer (lecturer
     * roster, student subject browse) shares one implementation. Plain
     * method (not an Attribute accessor) so Blade and PHP both call it
     * identically as $section->windowStatus(). Keep these three exact
     * lowercase keywords — do NOT rename 'opens' to 'opening', which is
     * reserved for exams (Exam::availabilityState()).
     */
    public function windowStatus(): string
    {
        $now = now();

        if ($now->lt($this->opens_at)) {
            return 'opens';
        }
        if ($now->gte($this->closes_at)) {
            return 'closed';
        }

        return 'open';
    }
}
