<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_is_blocked_from_the_lecturer_area(): void
    {
        $student = User::factory()->create(['role' => Role::Student]);

        $response = $this->actingAs($student)->get('/lecturer');

        $response->assertForbidden();
    }

    public function test_a_lecturer_is_blocked_from_the_student_area(): void
    {
        $lecturer = User::factory()->create(['role' => Role::Lecturer]);

        $response = $this->actingAs($lecturer)->get('/student');

        $response->assertForbidden();
    }

    public function test_a_lecturer_can_access_the_lecturer_area(): void
    {
        $lecturer = User::factory()->create(['role' => Role::Lecturer]);

        $response = $this->actingAs($lecturer)->get('/lecturer');

        $response->assertOk();
    }

    public function test_a_student_can_access_the_student_area(): void
    {
        $student = User::factory()->create(['role' => Role::Student]);

        $response = $this->actingAs($student)->get('/student');

        $response->assertOk();
    }
}
