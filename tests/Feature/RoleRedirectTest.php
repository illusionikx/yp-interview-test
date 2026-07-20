<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lecturer_is_redirected_to_the_lecturer_area(): void
    {
        $lecturer = User::factory()->create(['role' => Role::Lecturer]);

        $response = $this->actingAs($lecturer)->get('/dashboard');

        $response->assertRedirect(route('lecturer.home'));
    }

    public function test_a_student_is_redirected_to_the_student_area(): void
    {
        $student = User::factory()->create(['role' => Role::Student]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertRedirect(route('student.home'));
    }
}
