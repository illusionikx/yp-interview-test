<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'created_by' => User::factory()->lecturer(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'duration_minutes' => fake()->numberBetween(15, 90),
            'is_published' => false,
        ];
    }

    /**
     * Indicate that the exam is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the exam is currently within its availability window.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_from' => now()->subDay(),
            'available_until' => now()->addDays(7),
        ]);
    }

    /**
     * Indicate that the exam's availability window has not opened yet.
     */
    public function opening(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_from' => now()->addDay(),
            'available_until' => now()->addDays(7),
        ]);
    }

    /**
     * Indicate that the exam's availability window has already closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_from' => now()->subDays(7),
            'available_until' => now()->subDay(),
        ]);
    }
}
