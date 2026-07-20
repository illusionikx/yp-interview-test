<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'type' => QuestionType::Mcq,
            'body' => fake()->sentence(),
            'points' => 1,
            'position' => 0,
        ];
    }

    /**
     * Indicate that the question is multiple-choice, attaching four
     * options with exactly one flagged correct (D-08 fixture form).
     */
    public function mcq(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::Mcq,
        ])->afterCreating(function (Question $question) {
            Option::factory()->create([
                'question_id' => $question->id,
                'is_correct' => true,
            ]);

            Option::factory()->count(3)->create([
                'question_id' => $question->id,
                'is_correct' => false,
            ]);
        });
    }

    /**
     * Indicate that the question is open-text, with no options attached.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuestionType::Open,
        ]);
    }
}
