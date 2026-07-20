<?php

namespace App\Models;

use App\Exceptions\AttemptVanishedException;
use App\Services\AttemptGrader;
use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'user_id',
        'started_at',
        'submitted_at',
        'status',
        'score',
    ];

    // Status stays a plain string column this phase — the attempt
    // lifecycle state machine is Phase 4; no enum/business logic here.
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * The server-authoritative deadline (D-03) — always recomputed from
     * started_at + exam.duration_minutes, NEVER stored/read from the
     * client. Callers MUST have `exam` eager-loaded (loadMissing('exam'))
     * before calling this, or any of the methods below, to avoid an N+1
     * (04-RESEARCH.md Pitfall 4).
     */
    public function deadline(): Carbon
    {
        return $this->started_at->copy()->addMinutes($this->exam->duration_minutes);
    }

    /**
     * True once `now()` has reached or passed the deadline. Requires
     * `exam` eager-loaded — see deadline().
     */
    public function isExpired(): bool
    {
        return now()->greaterThanOrEqualTo($this->deadline());
    }

    /**
     * Display-only seed for the client countdown — never re-trusted as
     * the enforcement mechanism (D-03). Requires `exam` eager-loaded.
     */
    public function remainingSeconds(): int
    {
        return max(0, $this->deadline()->getTimestamp() - now()->getTimestamp());
    }

    /**
     * The SINGLE chokepoint where time-expiry flips an attempt from
     * in_progress to submitted (D-04/D-05). Called first in every
     * attempt-touching controller action. Idempotent and safe under
     * concurrent touches: re-reads the row with lockForUpdate inside a
     * transaction and re-checks status+expiry under the lock before
     * writing, so a racing auto-submit and manual reload can't both
     * finalize the same attempt. Requires `exam` eager-loaded.
     *
     * Deliberately an explicit method call, not a model booted()/saving
     * event — so "does this request finalize an expired attempt" stays
     * visible at the call site (project constraint, 04-RESEARCH.md).
     *
     * Shares its lock-then-check-then-update shape with finalize() via
     * lockAndFinalize() below — there is exactly one place that ever
     * writes status=submitted (04-04, checker directive: no duplicated
     * finalize implementation).
     *
     * @return bool true if this call just finalized the attempt.
     */
    public function finalizeIfExpired(): bool
    {
        if ($this->status !== 'in_progress' || ! $this->isExpired()) {
            return false;
        }

        return $this->lockAndFinalize(fn (self $locked) => $locked->isExpired());
    }

    /**
     * Student-initiated submit (TAK-04/T-04-02). Transactionally and
     * idempotently finalizes the attempt via the same lockAndFinalize()
     * shape as finalizeIfExpired() — a double-click or a second tab
     * racing this call is a no-op, not an error, because the locked
     * re-read's status check inside lockAndFinalize() catches it.
     *
     * @return bool true if this call just finalized the attempt.
     */
    public function finalize(): bool
    {
        return $this->lockAndFinalize(fn () => true);
    }

    /**
     * Shared lock-then-check-then-update primitive used by both
     * finalizeIfExpired() (lazy on-touch expiry) and finalize() (manual
     * submit). Re-reads the row with lockForUpdate inside a
     * DB::transaction (3 retries on deadlock); $guard receives the
     * locked row and decides whether finalize proceeds — false is an
     * idempotent no-op. submitted_at is clamped to deadline() so it is
     * never recorded past the server-authoritative deadline even when
     * this call itself arrives late.
     */
    private function lockAndFinalize(callable $guard): bool
    {
        return DB::transaction(function () use ($guard) {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            // T-09-01 (TOCTOU): the row vanished under this transaction
            // because a concurrent delete/reset committed between this
            // request's route-model binding and this lockForUpdate() read.
            // This is NOT the "already finalized by a racing request" case
            // — that one is handled below by the status === 'in_progress'
            // check and correctly returns false as an idempotent no-op. A
            // vanished row cannot be synced back to $this (there is
            // nothing to sync) and cannot be finalized, so the only honest
            // outcomes are a crash or a typed failure; INT-01 requires the
            // latter. Throwing here also deliberately skips the in-memory
            // sync at the bottom of this method — there is no authoritative
            // row to sync from, and leaving $this untouched is better than
            // inventing state. The enclosing DB::transaction's `3` is a
            // deadlock-retry count; this throw is not a deadlock, so it
            // propagates immediately without retrying, which is correct —
            // re-reading a deleted row three times finds it deleted three
            // times. Do not wrap this throw or change the retry count.
            if (! $locked) {
                throw new AttemptVanishedException;
            }

            $locked->setRelation('exam', $this->exam);

            $finalized = false;

            if ($locked->status === 'in_progress' && $guard($locked)) {
                $locked->update([
                    'status' => 'submitted',
                    'submitted_at' => now()->lessThan($locked->deadline())
                        ? now()
                        : $locked->deadline(),
                ]);
                $finalized = true;

                // Phase 5 hook (D-02) — same transaction, same row lock,
                // exactly-once: grades every MCQ answer and evaluates the
                // submitted->graded completeness transition atomically with
                // the status flip above. Covers both the manual-submit and
                // lazy-expiry finalize paths (both route through here).
                app(AttemptGrader::class)->handleFinalized($locked);
            }

            // ALWAYS sync the caller's in-memory copy to the authoritative
            // locked DB row — including the "already finalized by a racing
            // request" branch. Otherwise a caller (e.g. answer()) that
            // re-checks $this->status after this method would see a STALE
            // in_progress and wrongly proceed to write to a submitted
            // attempt (review blocker: autosave racing the client's own
            // auto-submit).
            $this->setRawAttributes($locked->getAttributes());
            $this->setRelation('exam', $locked->exam);
            $this->setRelation('answers', $locked->answers);

            return $finalized;
        }, 3);
    }
}
