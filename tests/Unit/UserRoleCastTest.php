<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_cast_to_role_enum_after_create_and_reload(): void
    {
        $lecturer = User::factory()->create(['role' => Role::Lecturer]);
        $reloaded = User::find($lecturer->id);

        $this->assertInstanceOf(Role::class, $reloaded->role);
        $this->assertTrue($reloaded->role === Role::Lecturer);
    }

    public function test_is_lecturer_and_is_student_helpers_reflect_stored_role(): void
    {
        $lecturer = User::factory()->create(['role' => Role::Lecturer]);
        $student = User::factory()->create(['role' => Role::Student]);

        $this->assertTrue($lecturer->isLecturer());
        $this->assertFalse($lecturer->isStudent());

        $this->assertTrue($student->isStudent());
        $this->assertFalse($student->isLecturer());
    }
}
