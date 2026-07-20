# Phase 9: v3.0 Foundations — Pattern Map

**Mapped:** 2026-07-17
**Files analyzed:** 12 new + 7 modified
**Analogs found:** 12 / 12

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|--------------------|------|-----------|-----------------|---------------|
| `app/Support/Semester.php` | utility (value object) | transform (date math) | `app/Models/Section.php` (`windowStatus()`, `name()` accessor) + `app/Models/Exam.php` (`availabilityState()`) | exact (pattern, not class type — Semester is not Eloquent) |
| `resources/views/components/toast.blade.php` | component | request-response (reads flash on page load) | `resources/views/components/status-pill.blade.php` (Blade component conventions) + `resources/views/components/auth-session-status.blade.php` (flash-reading precedent) | role-match |
| `resources/views/components/confirm-modal.blade.php` | component | event-driven (Alpine dispatch) | `resources/views/components/modal.blade.php` (must wrap, not rebuild) | exact |
| `resources/views/landing.blade.php` (new) | component/page | request-response | `resources/views/layouts/guest.blade.php` (shell shape) + `resources/views/layouts/navigation.blade.php` (dark-toggle markup to copy) | partial — needs a new dedicated shell, see below |
| `routes/web.php` (`/` route edit) | route | request-response | existing `Route::get('/', fn () => view('welcome'))` in same file | exact (edit in place) |
| `resources/views/auth/login.blade.php` (edit) | component/page | request-response | v3.md verbatim snippet (supplied) + existing `<x-input-error>`/`old()` pattern already used in this same file | exact |
| `app/Models/Attempt.php` (`lockAndFinalize()` null-guard) | model | CRUD (concurrency guard) | same method, same file — precedent is `finalize()`/`finalizeIfExpired()`'s existing "lock, check, act" style | exact (edit in place) |
| `app/Http/Controllers/Student/AttemptController.php` (`answer()` null-guard) | controller | request-response | same method, same file, lines 158-195 | exact (edit in place) |
| `tests/Unit/SemesterTest.php` | test (unit) | transform | `tests/Unit/WindowSemanticsTest.php` | exact |
| `tests/Feature/AttemptNullGuardTest.php` | test (feature) | CRUD/concurrency | `tests/Feature/Student/AttemptAnswerTest.php` (fixture builder pattern) | exact |
| `tests/Feature/LandingPageTest.php` | test (feature) | request-response | `tests/Feature/Auth/AuthenticationTest.php` | role-match |
| `tests/Feature/ToastTest.php` | test (feature) | request-response | `tests/Feature/Auth/AuthenticationTest.php` (guest routes) + any controller test with `assertSessionHas('status', ...)` | role-match |
| `tests/Feature/NoNativeDialogTest.php` | test (feature, static scan) | transform (file-content assertion, no HTTP/DB) | `tests/Unit/WindowSemanticsTest.php` (no-RefreshDatabase style) — closest in *shape* despite living in `tests/Feature` | partial |
| `tailwind.config.js` (edit) | config | build-time transform | same file, existing `theme.extend.fontFamily` block | exact (edit in place) |

## Pattern Assignments

### `app/Support/Semester.php` (utility, transform)

**Analog:** `app/Models/Section.php:63-97` (`name()` accessor + `windowStatus()`), `app/Models/Exam.php:129-141` (`availabilityState()`)

**Confirmed precedent — "computed, not stored" discipline** (`app/Models/Section.php:85-97`):
```php
/**
 * Computed, not stored — the half-open [opens_at, closes_at) window
 * state, extracted from the inline @php block previously duplicated
 * in lecturer/sections/index.blade.php so every consumer (lecturer
 * roster, student subject browse) shares one implementation. Plain
 * method (not an Attribute accessor) so Blade and PHP both call it
 * identically as $section->windowStatus().
 */
public function windowStatus(): string
{
    $now = now();

    if ($now->lt($this->opens_at)) {
        return 'opens';
    }
    if ($now->gte($this->closes_at)) {
        return 'closed';
    }

    return 'open';
}
```

**What to copy:**
- Doc-comment convention: explain *why* it's computed not stored, and name the sibling
  predicate it must stay consistent with (mirrors `Section::windowStatus()`'s comment
  referencing `Exam::availabilityState()`).
- Plain public method returning a primitive/enum-like string or, for `Semester`, a value
  object itself — no Eloquent `Attribute::make()` needed since `Semester` isn't a model
  attribute.
- Half-open interval comparisons (`lt`/`gte`, never `lte`/`gt`) — apply the same discipline
  to `Semester::startsAt()`/`endsAt()` bounds.
- `Section::name()` (`app/Models/Section.php:68-73`) is the accessor-composition precedent:
  build a display string from raw stored fields (`year`, `semester`, `sequence`) —
  `Semester::forDate()` reads exactly these two source-of-truth columns (`Section.year`,
  `Section.semester`), never a separate table.

**Namespace:** `App\Support` does not exist yet in this codebase — this file creates the
directory. No existing analog for the directory itself; place the class directly following
PSR-4 (`app/Support/Semester.php` → `App\Support\Semester`).

---

### `resources/views/components/toast.blade.php` (component, request-response)

**Analog:** `resources/views/components/status-pill.blade.php` (component conventions), `resources/views/components/auth-session-status.blade.php` (flash-reading precedent)

**Props/structure convention** (`status-pill.blade.php:1-21`):
```blade
@props(['status'])

@php
    $normalized = strtolower(trim((string) $status));
    $classes = match ($normalized) {
        'enrolled', 'published', 'open', 'available' => 'bg-green-100 ... dark:bg-green-900 ...',
        default => 'bg-gray-100 ... dark:bg-gray-700 ...',
    };
@endphp

<span {{ $attributes->merge(['class' => "rounded-full px-2.5 py-0.5 text-xs font-semibold {$classes}"]) }}>
    {{ $slot }}
</span>
```
Copy this shape: `@props([...])` declared first, a `@php` block computing derived state with a
`match()` (never string-interpolating raw dynamic content into `class=`), then markup using
`$attributes->merge()` where the component accepts pass-through attributes. `<x-toast>` has no
props (it reads `session()` directly, per CONTEXT.md's "zero controllers" constraint) so it
skips `@props()` entirely — go straight to the `@php` flash-reading block.

**Existing flash-reading precedent to check before writing (do not duplicate its behavior for
the 3 sentinel routes):**
```
resources/views/components/auth-session-status.blade.php
```
Read this file's exact `@props`/`@if(session('status'))` shape — `<x-toast>` must explicitly
exclude the same three sentinel strings (`verification-link-sent`, `password-updated`,
`profile-updated`) this component (and the two profile partials) test for, or both will fire
for the same flash.

**Alpine convention already established in this codebase** (`resources/views/layouts/navigation.blade.php:53-57`, the dark-toggle button):
```blade
x-data="{ dark: document.documentElement.classList.contains('dark') }"
@click="
    dark = ! dark;
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem('theme', dark ? 'dark' : 'light');
"
```
Mirror this style for the toast's `x-data`/`x-init`/`setTimeout` auto-dismiss and the
close-button `@click` — inline `x-data` object literal, no external Alpine component
registration file (there is none in this codebase; everything is inline `x-data="{...}"`).

---

### `resources/views/components/confirm-modal.blade.php` (component, event-driven)

**Analog — MUST wrap, not rebuild:** `resources/views/components/modal.blade.php` (read in full, 79 lines)

**How `<x-modal>` is invoked and driven today** (its full public contract, from the file
itself):
```blade
@props(['name', 'show' => false, 'maxWidth' => '2xl'])
...
x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
x-on:close.stop="show = false"
x-on:keydown.escape.window="show = false"
...
{{ $slot }}
```
It is opened purely by dispatching a window CustomEvent — **there is no existing Blade view
in this codebase currently invoking `<x-modal>`** (grep found zero current callers besides the
component itself), so there is no existing "before" example to copy call-site wiring from
apart from the 3 raw `confirm()` sites this phase migrates. The wrapper contract:
```blade
{{-- confirm-modal.blade.php skeleton --}}
@props(['name', 'title', 'body', 'confirmLabel' => 'Delete', 'cancelLabel' => 'Cancel', 'danger' => true])

<x-modal :name="$name" :max-width="'md'">
    <div class="p-6">
        <h2 class="text-lg font-semibold text-heading">{{ $title }}</h2>
        <p class="mt-2 text-sm text-body">{{ $body }}</p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" x-on:click="$dispatch('close-modal', '{{ $name }}')">{{ $cancelLabel }}</button>
            <button type="button" {{ $attributes->whereStartsWith('x-on:click')->first() }}>{{ $confirmLabel }}</button>
        </div>
    </div>
</x-modal>
```
Reuse `$name` as the same string both `open-modal`/`close-modal` events key on — do not invent
a second event name. Dispatch `open-modal` from each destructive form's `@submit.prevent`
handler exactly as `x-on:open-modal.window` expects (`window.dispatchEvent(new
CustomEvent('open-modal', { detail: name }))` or Alpine's `$dispatch('open-modal', name)`
shorthand, which bubbles to `window` via Alpine's event modifiers).

**Panel styling note (from `modal.blade.php:68`):** the existing panel is
`bg-white rounded-lg overflow-hidden shadow-xl ...` — **no dark-mode classes today**. Per
09-UI-SPEC.md this must gain `dark:bg-gray-800` as part of this new component (the underlying
`<x-modal>` primitive itself may also need this edit, or `<x-confirm-modal>` can override via
slotted content — executor's call per UI-SPEC).

**3 exact call sites to migrate (verified locations, current `onsubmit="return confirm(...)"` shape):**
```blade
{{-- resources/views/lecturer/exams/show.blade.php:54 --}}
<form method="POST" action="{{ route('lecturer.exams.destroy', $exam) }}" onsubmit="return confirm('{{ __('Delete this exam?') }}');">
    @csrf
    @method('DELETE')
    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400 text-sm">{{ __('Delete') }}</button>
</form>
```
```blade
{{-- resources/views/lecturer/exams/show.blade.php:79 --}}
<form method="POST" action="{{ route('lecturer.exams.questions.destroy', [$exam, $question]) }}" onsubmit="return confirm('{{ __('Delete this question?') }}');">
    @csrf
    @method('DELETE')
    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400 text-xs">{{ __('Delete') }}</button>
</form>
```
```blade
{{-- resources/views/lecturer/subjects/index.blade.php:38 --}}
<form action="{{ route('lecturer.subjects.destroy', $subject) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('Delete this subject?') }}');">
    @csrf
    @method('DELETE')
    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400">{{ __('Delete') }}</button>
</form>
```
All three: keep `@csrf`/`@method('DELETE')`/the POST action untouched; only replace the
`onsubmit="return confirm(...)"` attribute with an Alpine intercept + `<x-confirm-modal>` pair.

---

### `resources/views/landing.blade.php` + layout question (NAV-01, UX-01)

**Analog for shell shape:** `resources/views/layouts/guest.blade.php` (30 lines, full text below) vs. `resources/views/layouts/app.blade.php`

```blade
{{-- layouts/guest.blade.php — entire file --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        ...
        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div><a href="/"><x-application-logo class="w-20 h-20 fill-current text-gray-500" /></a></div>
            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
```

**Decisive evidence for the open research question ("does landing reuse `layouts.guest` or need
its own shell?"):**
1. `layouts/guest.blade.php` **hard-codes a centered narrow card** (`sm:max-w-md`, `flex
   flex-col sm:justify-center items-center`) with **no top bar / nav slot at all** — there is
   no `@include('layouts.navigation')`-equivalent here, unlike `layouts/app.blade.php` which
   does `@include('layouts.navigation')` at line 33.
2. `layouts/guest.blade.php` is **missing the pre-paint dark-mode bootstrap script** that
   `layouts/app.blade.php` has (lines 14-26 of `app.blade.php`) — today only the authenticated
   shell supports dark mode without a flash-of-wrong-theme. The landing page needs the toggle
   (Decision: "top bar: title + dark-mode toggle + Sign in"), so it needs *both* the bootstrap
   script and a top bar — neither of which `guest.blade.php` has.
3. The login page (which *does* stay on `layouts.guest` per UI-SPEC) needs the *centered card*
   behavior unchanged — so `guest.blade.php` cannot be repurposed into a full-bleed hero shell
   without breaking the login page's current look.

**Recommendation (confirmed by evidence above, matches RESEARCH.md's Open Question #2
recommendation):** create a small dedicated shell — either a new
`resources/views/layouts/landing.blade.php` copying `guest.blade.php`'s `<head>` block verbatim
(fonts, `@vite`, meta tags) plus `app.blade.php`'s dark-mode bootstrap script, but a full-bleed
`<body>` (no centered card wrapper), or have `landing.blade.php` assemble its own `<html>` shell
inline reusing only the `<head>` markup. Do not branch `guest.blade.php`'s existing centered-card
body to conditionally support both shapes — the login page's shell must stay exactly as-is.

**Dark-toggle markup to copy verbatim into the new landing top bar** (`layouts/navigation.blade.php:50-67`):
```blade
<!-- Dark-mode toggle (UI-02) — mirrors 07-01's pre-paint bootstrap script -->
<button
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    @click="
        dark = ! dark;
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    "
    aria-label="{{ __('Toggle dark mode') }}"
    class="inline-flex items-center justify-center rounded-lg p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
>
    <svg x-show="! dark" ...><!-- sun icon --></svg>
    <svg x-show="dark" x-cloak ...><!-- moon icon --></svg>
</button>
```

**Route analog** (`routes/web.php:7-9`, current):
```php
Route::get('/', function () {
    return view('welcome');
});
```
Edit in place to:
```php
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
});
```
This is the exact file/line to edit — no new route file needed, same pattern as the existing
`DashboardController` registration two lines below it.

---

### `resources/views/auth/login.blade.php` (edit, NAV-02)

Current file already uses the `<x-input-error>`/`old()`/`@csrf` pattern (Breeze scaffold
default) — read it directly before editing; the v3.md snippet's Flowbite markup replaces the
Breeze Tailwind-forms markup class-for-class but the underlying `<form method="POST"
action="{{ route('login') }}">`, `@csrf`, `:value="old('email')"`, `<x-input-error
:messages="$errors->get('email')" />` structure must be preserved verbatim — do not strip
Breeze's validation display to match the static v3.md snippet's inert markup.

---

### `app/Models/Attempt.php::lockAndFinalize()` null-guard (INT-01)

**Exact crash site** (`app/Models/Attempt.php:139-141`):
```php
return DB::transaction(function () use ($guard) {
    $locked = self::whereKey($this->id)->lockForUpdate()->first();
    $locked->setRelation('exam', $this->exam);   // <-- crashes if $locked is null
```

**Guard to insert**, matching this method's existing comment-density convention (long
doc-comments explaining *why*, immediately above the line that needs it):
```php
$locked = self::whereKey($this->id)->lockForUpdate()->first();

if (! $locked) {
    // The row vanished under us (concurrent delete/reset racing this
    // lock). Treat as an idempotent no-op — never crash a background
    // timer/autosave request just because the record disappeared.
    return false;
}

$locked->setRelation('exam', $this->exam);
```

### `app/Http/Controllers/Student/AttemptController.php::answer()` second null-guard (INT-01)

**Exact crash site** (`AttemptController.php:171-176`, inside the same file's existing
`DB::transaction` closure — this is a SEPARATE lock read, not a caller of
`lockAndFinalize()`):
```php
$saved = DB::transaction(function () use ($attempt, $data) {
    $locked = Attempt::whereKey($attempt->id)->lockForUpdate()->first();

    if ($locked->status !== 'in_progress') {   // <-- crashes if $locked is null
        return false;
    }
    ...
});
```
Guard shape mirrors the existing `!== 'in_progress'` short-circuit already in this method —
just add the null check to the same `if`:
```php
if (! $locked || $locked->status !== 'in_progress') {
    return false;
}
```
The existing `422 {'expired': true}` response shape immediately below (lines 186-192) is the
analog for how this guard's "no longer available" case should surface to the client — reuse
that JSON contract rather than inventing a new key, per RESEARCH.md's Assumption A2.

---

## Test Analogs

### `tests/Unit/SemesterTest.php` — analog: `tests/Unit/WindowSemanticsTest.php`

```php
class WindowSemanticsTest extends TestCase
{
    public function test_exam_with_null_bounds_is_always_available(): void
    {
        $exam = new Exam(['available_from' => null, 'available_until' => null]);
        $this->assertTrue($exam->isAvailableNow());
    }

    public function test_exam_is_available_at_exact_available_from_instant(): void
    {
        $from = now();
        $this->travelTo($from);
        $exam = new Exam(['available_from' => $from, 'available_until' => null]);
        $this->assertTrue($exam->isAvailableNow());
    }
}
```
Copy: **no `RefreshDatabase`**, plain object construction via mass assignment (or, for
`Semester`, plain constructor args), `$this->travelTo(...)` for boundary-instant tests, one
assertion focus per test method, descriptive `test_<condition>_<expected>` method names.

### `tests/Feature/AttemptNullGuardTest.php` — analog: `tests/Feature/Student/AttemptAnswerTest.php`

```php
class AttemptAnswerTest extends TestCase
{
    use RefreshDatabase;

    private function mcqAttemptFixture(int $durationMinutes = 30): array
    {
        $section = Section::factory()->create();
        $exam = Exam::factory()->published()->create(['duration_minutes' => $durationMinutes]);
        $exam->sections()->sync([$section->id]);
        $student = User::factory()->student()->create();
        $section->enrollments()->attach($student->id, ['status' => EnrollmentStatus::Enrolled]);

        $question = Question::factory()->mcq()->create(['exam_id' => $exam->id]);
        $option = $question->options()->first();

        $attempt = Attempt::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        return [$student, $attempt, $question, $option];
    }

    public function test_an_answer_after_the_deadline_is_rejected(): void
    {
        [$student, $attempt, $question, $option] = $this->mcqAttemptFixture(10);
        $this->travelTo($attempt->started_at->copy()->addMinutes(10)->addMinute());

        $response = $this->actingAs($student)->post(route('student.attempts.answer', $attempt), [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('answers', 0);
    }
}
```
Copy: `use RefreshDatabase`, a private `xFixture()` helper building `Section::factory()` →
`Exam::factory()->published()` → sync sections → `User::factory()->student()` → attach
enrollment → `Question::factory()->mcq()` → `Attempt::factory()->create([...])`. For the
null-guard test, extend this fixture with `$attempt->delete()` (or raw
`DB::table('attempts')->where('id', $attempt->id)->delete()`) immediately before calling
`->finalize()` / posting to `student.attempts.answer` to simulate the concurrent-delete race,
then assert no exception and the expected redirect/422 shape.

### `tests/Feature/LandingPageTest.php` / `tests/Feature/ToastTest.php` — analog: `tests/Feature/Auth/AuthenticationTest.php`

```php
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
```
Copy: `use RefreshDatabase` even for guest-only routes (project convention, not strictly
required for a stateless GET but consistent with every other Feature test in this codebase),
plain `$this->get('/')` / `$this->actingAs($user)->get('/')` + `assertStatus`/`assertRedirect`/
`assertSee`/`assertSessionHas` assertions, one behavior per test method.

### `tests/Feature/NoNativeDialogTest.php` — no direct analog; closest shape

No existing "scan the view files" test exists in this codebase. Closest available convention
is `WindowSemanticsTest`'s **no-`RefreshDatabase`, no-HTTP-call** style (pure PHP assertions,
`tests/Unit`-flavored despite living in `tests/Feature` per RESEARCH.md's Wave-0 gap table).
Suggested shape (not an existing pattern, flagged as new):
```php
class NoNativeDialogTest extends TestCase
{
    public function test_no_view_uses_native_confirm_or_alert(): void
    {
        $violations = [];
        foreach (\Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $file) {
            $contents = file_get_contents($file->getPathname());
            if (str_contains($contents, 'confirm(') || str_contains($contents, 'alert(')) {
                $violations[] = $file->getRelativePathname();
            }
        }
        $this->assertEmpty($violations, 'Native confirm()/alert() found in: '.implode(', ', $violations));
    }
}
```

---

## Shared Patterns

### Flash convention (toast trigger)
**Source:** grepped across `app/Http/Controllers/**` — 57 call sites of `->with('status', ...)` / `session('status')`, 0 of `session('success')`.
**Apply to:** `<x-toast>` only — read `session('status')` + `session('error')`, never `session('success')`.

### Dark-mode toggle
**Source:** `resources/views/layouts/navigation.blade.php:53-67`
**Apply to:** the new landing top bar — copy verbatim, do not reinvent the localStorage/class-toggle logic.

### Modal primitive (open/close event contract)
**Source:** `resources/views/components/modal.blade.php` (full file — `@props(['name','show','maxWidth'])`, `open-modal`/`close-modal` window events, focus trap, Escape-to-close)
**Apply to:** `<x-confirm-modal>` must wrap `<x-modal :name="...">`, never re-implement overlay/focus-trap/transitions.

### Blade component `@props()` + `match()` convention
**Source:** `resources/views/components/status-pill.blade.php`
**Apply to:** any new component (`toast.blade.php`, `confirm-modal.blade.php`) — declare `@props([...])` first, compute derived state in a `@php` block using `match()` for any variant-selection logic (never raw string interpolation into a `class` attribute).

### Feature test fixture-building
**Source:** `tests/Feature/Student/AttemptAnswerTest.php` (private `xFixture()` helper composing `Section::factory()` → `Exam::factory()->published()` → `User::factory()->student()` → enrollment attach → `Question::factory()->mcq()` → `Attempt::factory()`)
**Apply to:** `AttemptNullGuardTest.php` (reuse the same fixture, add a mid-test row delete).

## No Analog Found

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `tests/Feature/NoNativeDialogTest.php` | test (static scan) | file-content assertion | No existing test in this codebase scans view file contents rather than making HTTP/DB assertions; shape above is newly proposed, following `WindowSemanticsTest`'s "no RefreshDatabase" minimalism as the closest available convention. |
| `resources/views/layouts/landing.blade.php` (or landing's own shell) | layout | request-response | Neither existing layout (`guest.blade.php`, `app.blade.php`) fits a full-bleed hero + top bar without either losing dark-mode bootstrap (guest) or pulling in the full authenticated navbar (app) — a new small shell is required; see evidence above. |

## Metadata

**Analog search scope:** `app/Models`, `app/Http/Controllers/Student`, `resources/views/components`, `resources/views/layouts`, `resources/views/lecturer`, `tests/Unit`, `tests/Feature/Student`, `tests/Feature/Auth`, `routes/web.php`, `tailwind.config.js`.
**Files scanned:** ~20 read directly, plus grep sweeps for `confirm(`, `session('status'`, `windowStatus`/`availabilityState`.
**Pattern extraction date:** 2026-07-17
