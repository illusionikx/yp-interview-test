<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Section>
 */
class SectionFactory extends Factory
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
            'year' => fake()->numberBetween(2024, 2027),
            'semester' => fake()->numberBetween(1, 2),
            'sequence' => 1,
            'capacity' => 30,
            // Open window by default so section/enrollment tests can
            // enroll immediately without extra state setup.
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(14),
        ];
    }
}
