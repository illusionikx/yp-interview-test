<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a lecturer.
     */
    public function lecturer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Lecturer,
        ]);
    }

    /**
     * Indicate that the user is a student.
     *
     * Explicitly rebuilds the name from firstName()/lastName() rather than
     * trusting definition()'s fake()->name() — Faker's default Person
     * formats occasionally prepend a title ("Dr.", "Mr.", "Mrs.", ...) on
     * their own, which would silently violate the SEED-01 exclusivity rule
     * (zero titled students) for reasons unrelated to titled() below.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Student,
            'name' => fake()->firstName().' '.fake()->lastName(),
        ]);
    }

    /**
     * SEED-01: prefixes/suffixes the name with an academic title (Dr./Prof./
     * Assoc. Prof./PhD). Reserved for bulk-seeded LECTURERS only — no
     * student factory call ever chains this state, which is what keeps the
     * SEED-01 title-exclusivity rule (zero titled students) structurally
     * true rather than merely accidental. Built from firstName()/lastName()
     * (not name()) so the title is never accidentally doubled by one of
     * Faker's own title-prefixed name formats.
     */
    public function titled(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->boolean(70)
                ? fake()->randomElement(['Dr.', 'Prof.', 'Assoc. Prof.']).' '.fake()->firstName().' '.fake()->lastName()
                : fake()->firstName().' '.fake()->lastName().', PhD',
        ]);
    }
}
