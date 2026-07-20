<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory()->published(),
            'user_id' => User::factory()->student(),
            'started_at' => now(),
            'submitted_at' => null,
            'status' => 'in_progress',
            'score' => null,
        ];
    }

    /**
     * Indicate that the attempt has been submitted (D-01/D-02 lifecycle —
     * no new columns, just the existing status/submitted_at pair).
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that the attempt has already been graded (D-03/D-08) — lets
     * a test build a fully-graded attempt directly, without driving the
     * whole AttemptGrader flow. No graded_by/graded_at: the Phase-1
     * answers/attempts schema has no such columns; score is the sole
     * authoritative grade field.
     */
    public function graded(int $score = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graded',
            'submitted_at' => now(),
            'score' => $score,
        ]);
    }
}
