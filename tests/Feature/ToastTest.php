<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UX-03 executable spec for the <x-toast> component (plan 09-07). These tests seed flashes
 * directly via withSession() rather than driving a real controller action — 09-CONTEXT.md locks
 * "this touches zero controllers", so the toast's rendering contract is isolated from any
 * particular controller.
 *
 * RED as of this plan (09-03): tests 1, 2, 3, 4 and 8 fail because <x-toast> does not exist yet.
 * Tests 5, 6 and 7 may pass today by accident (nothing toasts anything yet, so the sentinel is
 * trivially "not toasted") — they become meaningful regression guards once plan 09-07 lands the
 * component.
 */
class ToastTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_status_flash_renders_as_a_toast(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)
            ->withSession(['status' => 'Exam created.'])
            ->get(route('lecturer.home'));

        $response->assertSee('Exam created.');
        $response->assertSee('aria-label="Dismiss"', false);
    }

    public function test_a_status_flash_renders_exactly_once(): void
    {
        // The duplicate-render guard, and the reason plan 09-10 exists. lecturer/exams/index.blade.php
        // currently renders its own inline @if (session('status')) banner, and 10 other views do the
        // same. Once <x-toast> is in layouts/app.blade.php, both fire and the message renders twice —
        // two styles on one page, which fails UX-03's "one consistent style" bar and re-creates the
        // FIX-03 confusion the toaster exists to remove. Plan 09-09 deletes the inline banners; this
        // assertion is what proves it happened.
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)
            ->withSession(['status' => 'Exam created.'])
            ->get(route('lecturer.home'));

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'Exam created.'));
    }

    public function test_an_error_flash_renders_as_a_toast(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)
            ->withSession(['error' => 'That section is full.'])
            ->get(route('lecturer.sections.index'));

        $response->assertSee('That section is full.');
    }

    public function test_an_error_flash_renders_exactly_once(): void
    {
        // lecturer/sections/show.blade.php and three student views currently inline session('error')
        // too.
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)
            ->withSession(['error' => 'That section is full.'])
            ->get(route('lecturer.sections.index'));

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'That section is full.'));
    }

    public function test_the_profile_updated_sentinel_does_not_render_as_a_toast(): void
    {
        // Landmine: profile/partials/update-profile-information-form.blade.php checks session('status')
        // === 'profile-updated' by exact equality and renders its own inline "Saved." confirmation.
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['status' => 'profile-updated'])
            ->get(route('profile.edit'));

        $response->assertDontSee('profile-updated');
        $response->assertSee('Saved.');
    }

    public function test_the_password_updated_sentinel_does_not_render_as_a_toast(): void
    {
        // Landmine: profile/partials/update-password-form.blade.php checks session('status') ===
        // 'password-updated' by exact equality and renders its own inline "Saved." confirmation.
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['status' => 'password-updated'])
            ->get(route('profile.edit'));

        $response->assertDontSee('password-updated');
        $response->assertSee('Saved.');
    }

    public function test_the_verification_link_sent_sentinel_does_not_render_as_a_toast(): void
    {
        // Landmine: auth/verify-email.blade.php checks session('status') == 'verification-link-sent'
        // and renders its own inline confirmation sentence. This one renders through
        // layouts/guest.blade.php, so it also proves the guest shell's toast honours the same
        // exclusion list as the app shell.
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->withSession(['status' => 'verification-link-sent'])
            ->get(route('verification.notice'));

        $response->assertDontSee('verification-link-sent');
        $response->assertSee('A new verification link has been sent to the email address you provided during registration.');
    }

    public function test_the_toast_escapes_html_in_flash_text(): void
    {
        // T-09-02 (Tampering/XSS): pins that the component renders flash text through Blade's
        // escaping echo and never through a raw echo.
        $lecturer = User::factory()->lecturer()->create();

        $response = $this->actingAs($lecturer)
            ->withSession(['status' => '<b>bold</b>'])
            ->get(route('lecturer.home'));

        $response->assertDontSee('<b>bold</b>', false);
        $response->assertSee('&lt;b&gt;bold&lt;/b&gt;', false);
    }
}
