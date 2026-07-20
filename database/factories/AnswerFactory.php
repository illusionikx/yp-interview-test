<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => Attempt::factory(),
            'question_id' => Question::factory(),
            'selected_option_id' => null,
            'answer_text' => fake()->sentence(),
            // Grading fields are never populated by this phase (Phase 5 owns them).
            'is_correct' => null,
            'score' => null,
        ];
    }

    /**
     * An ungraded open-text answer awaiting a lecturer (D-01/D-04). Only the
     * student's text is recorded here — is_correct/score stay null, exactly
     * like the base state, until AttemptGrader/GradeAnswerRequest (Wave 2-4)
     * writes a score.
     */
    public function openText(): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_option_id' => null,
            'answer_text' => fake()->sentence(),
        ]);
    }

    /**
     * Records the student's SELECTION of the passed question's correct
     * Option — never the grade itself. Grading is what AttemptGrader
     * computes (D-01); this factory only builds the pre-grade fixture.
     */
    public function mcqCorrect(Question $question): static
    {
        return $this->state(fn (array $attributes) => [
            'question_id' => $question->id,
            'selected_option_id' => $question->options()->where('is_correct', true)->value('id'),
            'answer_text' => null,
        ]);
    }

    /**
     * Records the student's SELECTION of one of the passed question's
     * non-correct Options — never the grade itself (D-01).
     */
    public function mcqIncorrect(Question $question): static
    {
        return $this->state(fn (array $attributes) => [
            'question_id' => $question->id,
            'selected_option_id' => $question->options()->where('is_correct', false)->value('id'),
            'answer_text' => null,
        ]);
    }
}
