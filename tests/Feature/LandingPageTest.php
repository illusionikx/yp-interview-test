<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_renders_for_a_guest(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('landing');
    }

    public function test_the_landing_page_shows_the_app_title_and_subtitle(): void
    {
        $response = $this->get('/');

        $response->assertSee('Online Examination Portal');
        $response->assertSee('for Yayasan Peneraju Technical Assessment');
    }

    public function test_the_landing_page_title_tag_names_the_app(): void
    {
        $response = $this->get('/');

        $response->assertSee('<title>Online Examination Portal</title>', false);
    }

    public function test_the_landing_page_links_to_login(): void
    {
        // Assert the exact "Sign in" CTA copy from 09-UI-SPEC.md's Copywriting Contract, not just
        // the presence of a route('login') href — Breeze's default welcome.blade.php already links
        // to route('login') under a "Log in" label, which would make a bare href check a false-RED
        // pass against the wrong markup.
        $response = $this->get('/');

        $response->assertSee('Sign in');
        $response->assertSee(route('login'), false);
    }

    public function test_the_landing_page_does_not_render_the_authenticated_navbar(): void
    {
        $response = $this->get('/');

        $response->assertDontSee(route('logout'), false);
    }

    public function test_an_authenticated_student_is_redirected_from_the_landing_page_to_the_dashboard(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_an_authenticated_lecturer_is_redirected_from_the_landing_page_to_the_dashboard(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $this->actingAs($lecturer)->get('/')->assertRedirect(route('dashboard'));
    }
}
