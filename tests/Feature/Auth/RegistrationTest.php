<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_always_creates_a_student_even_if_role_is_posted(): void
    {
        $response = $this->post('/register', [
            'name' => 'Aspiring Lecturer',
            'email' => 'aspiring-lecturer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'lecturer',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'aspiring-lecturer@example.com')->firstOrFail();

        $this->assertSame(Role::Student, $user->role);
    }
}
