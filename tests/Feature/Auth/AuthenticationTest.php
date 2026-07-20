<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_the_login_screen_renders_the_flowbite_card(): void
    {
        $response = $this->get('/login');

        $response->assertSee('Sign in to our platform');
        $response->assertSee('Login to your account');
    }

    public function test_the_login_card_links_to_the_register_route(): void
    {
        $response = $this->get('/login');

        $response->assertSee('Not registered?');
        $response->assertSee(route('register'), false);
    }

    public function test_the_login_card_links_to_the_password_reset_route(): void
    {
        $response = $this->get('/login');

        $response->assertSee('Lost Password?');
        $response->assertSee(route('password.request'), false);
    }

    public function test_the_login_card_uses_the_ported_design_tokens(): void
    {
        // This asserts the ported UI-03 token class names reach the markup. It deliberately does
        // NOT prove they emit CSS — that is UI-03's separate compiled-CSS build gate, owned by plan
        // 09-06. A green result here must not be mistaken for UI-03 being satisfied.
        $response = $this->get('/login');

        $response->assertSee('bg-neutral-primary-soft', false);
        $response->assertSee('rounded-base', false);
    }

    public function test_the_login_form_still_posts_to_the_login_route_with_csrf(): void
    {
        $response = $this->get('/login');

        $response->assertSee('action="'.route('login').'"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_a_failed_login_repopulates_the_email_and_shows_an_inline_error(): void
    {
        // Per 09-UI-SPEC.md, form validation errors stay inline per-field via <x-input-error> —
        // UX-03's toast governs create/save/delete, not form validation.
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');

        $followUp = $this->get('/login');
        $followUp->assertSee($user->email, false);
    }
}
