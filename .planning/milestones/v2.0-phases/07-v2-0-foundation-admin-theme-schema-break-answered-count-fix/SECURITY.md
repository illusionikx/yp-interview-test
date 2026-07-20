# Phase 7 Security Verification — v2.0 Foundation (Admin Theme, Schema Break & Answered-Count Fix)

**Verified:** 2026-07-16
**ASVS Level:** 1
**Block on:** high
**Method:** Live code inspection (grep + Read of cited files) and live command execution (`php artisan test`, `php artisan migrate:fresh --seed`) — not trust of PLAN.md/SUMMARY.md/REVIEW.md prose. Every "mitigate" threat below is closed only where a grep match or a passing live test was independently reproduced in this session.

## Verdict: SECURED — 25/25 threats CLOSED, 0 OPEN

The phase underwent a code review (`07-REVIEW.md`) that found one CRITICAL (CR-01, cross-subject exam-assignment leak) and four WARNING findings. `07-REVIEW-FIX.md` claims all five were fixed. This audit independently re-verified every fix in the live tree (not from the report) and re-ran the full test suite and a clean reseed. All checks passed live in this session:

- `php artisan test --filter="ExamVisibilityRegressionTest|SectionControllerTest|SubjectLecturerTest|ExamAssignmentTest|DomainSchemaTest|DatabaseSeederTest"` → 35 passed (106 assertions)
- `php artisan test` (full suite) → **187 passed, 488 assertions, 0 failures**
- `php artisan migrate:fresh --seed` → exit code 0, clean from empty database

---

## Threat Verification

### Plan 07-01 — Flowbite theme, dark mode, FIX-01

| Threat ID | Category | Disposition | Verdict | Evidence |
|---|---|---|---|---|
| T-07-SC | Tampering (npm supply chain) | mitigate | CLOSED | `package.json:20` pins `"flowbite": "^4.0.2"`; `npm view flowbite` confirms `4.0.2` is the current published version, no signature of tampering; wired via `tailwind.config.js:3,11,24` and `resources/js/app.js:4` exactly as declared. |
| T-07-01 | Info Disclosure/XSS (status-pill) | mitigate | CLOSED | `resources/views/components/status-pill.blade.php:10-15` — fixed `match()` allowlist over 4 palettes, `default => gray`; no raw status ever reaches the `class` attribute; label rendered via escaped `{{ $slot }}` at line 19 (no `{!! !!}` anywhere in the file). |
| T-07-02 | Info Disclosure (dark-mode localStorage) | accept | CLOSED | Accepted risk — see Accepted Risks Log below. |
| T-07-SC-view | Tampering (Alpine `answeredCount` DOM state) | accept | CLOSED | Accepted risk — see Accepted Risks Log below. Confirmed in code: `resources/views/student/attempts/show.blade.php:202-206` — the Submit button is gated only by `autoSubmitting`, never by `answeredCount`; server-side `submit`/`answer` endpoints re-check state independently (`app/Http/Controllers/Student/AttemptController.php`). A tampered client count cannot skip or force submission. |

### Plan 07-02 — RED test contract (ENR-08, SEC-03, schema)

| Threat ID | Category | Disposition | Verdict | Evidence |
|---|---|---|---|---|
| T-07-03 | EoP (test-coverage gap) — ENR-08 list-vs-gate | mitigate | CLOSED | `tests/Feature/Student/ExamVisibilityRegressionTest.php:29-56` — `#[DataProvider('enrollmentStates')]` over 4 states, asserts `assertSame($listVisible, $gateVisible)`. Ran live: 4/4 pass. |
| T-07-04 | EoP (test-coverage gap) — SEC-03 ownership | mitigate | CLOSED | `tests/Feature/Lecturer/SubjectLecturerTest.php` — asserts multi-lecturer assignment, assigned-lecturer-can-manage, non-assigned-lecturer 403 on create/edit/assign/unassign. Ran live: 8/8 pass. |
| T-07-05 | Tampering (test-coverage gap) — schema shape | mitigate | CLOSED | `tests/Feature/DomainSchemaTest.php` — asserts table set, `users` has `role` and NOT `classroom_id`, `enrollments` unique(`section_id`,`user_id`). Ran live: 5/5 pass. |

### Plan 07-03 — Migrations, models, `scopeVisibleTo`

| Threat ID | Category | Disposition | Verdict | Evidence |
|---|---|---|---|---|
| T-07-06 | EoP/Info Disclosure — `scopeVisibleTo` divergence | mitigate | CLOSED | `app/Models/Exam.php:82-90` — single `scopeVisibleTo()` predicate (`is_published` + `whereHas('sections.enrollments', status=Enrolled)`). Consumed identically by `app/Http/Controllers/Student/ExamController.php:20` (list), `app/Policies/ExamPolicy.php:19-23` (gate), `app/Policies/AttemptPolicy.php:37-41` (gate). Grepped `visibleTo` across `app/` — no other call site re-derives visibility. `ExamVisibilityRegressionTest` green confirms list==gate live. |
| T-07-07 | EoP — `enrollments.status` semantics | mitigate | CLOSED | `app/Enums/EnrollmentStatus.php` — backed enum (Enrolled/Withdrawn/Rejected); `app/Models/Enrollment.php:15-20` casts `status` via `casts()`. Grepped `app/Http` for any enrollment write path — none exists in Phase 7 (student self-enroll is Phase 8, confirmed absent from `routes/`), so only seeder/factory can set status, using the enum. |
| T-07-08 | Tampering (mass assignment) — `User::$fillable` | mitigate | CLOSED | `app/Models/User.php:28-33` — `$fillable = ['name','email','password','role']`, no `classroom_id`. `database/migrations/2026_07_15_100003_add_role_to_users_table.php` confirms the migration adds only `role`, never re-adds `classroom_id`. Registration hardcodes role — see mass-assignment section below. |
| T-07-09 | Info Disclosure — `Section.name` drift | accept | CLOSED | Accepted risk — see Accepted Risks Log below. Confirmed in code: `app/Models/Section.php:68-73` — `name` is a live `Attribute::make(get: ...)` accessor over `year`/`semester`/`sequence`, no stored/denormalized column exists to drift. |

### Plan 07-04 — Section CRUD, subject-lecturer assignment, exam-assignment swing

| Threat ID | Category | Disposition | Verdict | Evidence |
|---|---|---|---|---|
| T-07-10 | EoP (IDOR) — `SectionController` write actions | mitigate | CLOSED | `app/Http/Requests/Lecturer/StoreSectionRequest.php:20-25` and `UpdateSectionRequest.php:21-26` — `authorize()` checks `$subject->lecturers()->whereKey($this->user()->id)->exists()` (genuine per-subject ownership, NOT `return true`). `app/Http/Controllers/Lecturer/SectionController.php:111-119` (`destroy`) applies the same inline check. **WR-01 gap (GET `create()`/`edit()` had no ownership check) is fixed**: lines 39-44 (`create()`) and 83-89 (`edit()`) now `abort_unless(...403)` before rendering. Live-confirmed by `SectionControllerTest`: "a lecturer not assigned to the subject cannot view the create form" / "...edit form" — both pass. |
| T-07-11 | EoP — `SubjectLecturerController` | mitigate | CLOSED | `app/Http/Requests/Lecturer/AssignLecturerRequest.php:25-30` — same per-subject ownership `authorize()`; `rules()` restricts `user_id` to `Rule::exists('users','id')->where('role', Role::Lecturer->value)`. `app/Http/Controllers/Lecturer/SubjectLecturerController.php:34-41` (`destroy`/unassign) applies the same inline ownership check (no Form Request backs it). `SubjectLecturerTest`: "forbidden from assigning/unassigning lecturers" — both pass. |
| T-07-12 | Tampering (mass assignment) — `Section::create`/`AssignExamRequest` | mitigate | CLOSED | **CR-01 gap is fixed.** `app/Http/Requests/Lecturer/AssignExamRequest.php:40-44` — `section_ids.*` now validated via `Rule::exists('sections','id')->where('subject_id', $this->route('exam')->subject_id)`, constraining assignment to the exam's own subject. `app/Http/Controllers/Lecturer/ExamController.php:67-69` (`show()`) now lists only `Section::where('subject_id', $exam->subject_id)` (was previously unfiltered — the CR-01 leak). Regression test `tests/Feature/Lecturer/ExamAssignmentTest.php` ("assignment rejects a section from a different subject") passes live, confirming a foreign-subject section id is now rejected with a 422/validation error, not silently synced. |
| T-07-13 | Tampering/Availability — orphaned classroom_id roster endpoint | mitigate | CLOSED | Confirmed absent from disk: `app/Http/Controllers/Lecturer/ClassroomRosterController.php`, `app/Http/Requests/Lecturer/AssignStudentRequest.php`, and the `classrooms/{classroom}/students` routes are all deleted (verified via `ls` — no such file exists). |

### Plan 07-05 — Flowbite navbar, section/subject-lecturer views

| Threat ID | Category | Disposition | Verdict | Evidence |
|---|---|---|---|---|
| T-07-14 | EoP — section/lecturer-assignment forms | mitigate | CLOSED | UI forms post to the routes verified under T-07-10/T-07-11, whose Form Requests/controllers are the real enforcement (independent of what the view renders). |
| T-07-15 | Info Disclosure (XSS) — status-pill + names | mitigate | CLOSED | `resources/views/components/status-pill.blade.php` (see T-07-01); user-authored names (subject/section/lecturer) render via `{{ }}` throughout the reviewed views — grepped for `{!!` in `resources/views/lecturer/` — no matches. |
| T-07-16 | Info Disclosure — dark-mode toggle state | accept | CLOSED | Accepted risk — see Accepted Risks Log below. |

### Plan 07-06 — Content view reskin + classroom→section sweep

| Threat ID | Category | Disposition | Verdict | Evidence |
|---|---|---|---|---|
| T-07-17 | EoP/Info Disclosure — `student/exams/index` visibility | mitigate | CLOSED | `resources/views/student/exams/index.blade.php:11-24` — renders only the controller-provided `$exams` collection (`@forelse ($exams as $exam)`); no `Exam::`/`Section::`/`Enrollment::` query anywhere in the view (grepped, zero matches). |
| T-07-18 | Info Disclosure (XSS) — user-authored text + status-pill | mitigate | CLOSED | Same escaped-Blade pattern confirmed (T-07-01/T-07-15); no `{!! !!}` introduced in the reskinned views. |
| T-07-19 | Info Disclosure — correct-option leakage | mitigate | CLOSED | `app/Http/Controllers/Student/AttemptController.php:76,89-90` — explicit column-whitelisted view-model, options selected as `['id','question_id','body']` only (`is_correct` never selected). Confirmed the reskin (`resources/views/student/attempts/show.blade.php`) renders only `$option->body`/`$option->id` — no `is_correct` field reaches the Blade template or the Alpine JSON. This is the Phase-4 invariant, untouched by the Phase 7 reskin. |

### Plan 07-07 — Seeder rewrite, lecturer test sweep

| Threat ID | Category | Disposition | Verdict | Evidence |
|---|---|---|---|---|
| T-07-20 | Availability/Tampering — `DatabaseSeeder` vs v2.0 schema | mitigate | CLOSED | Live-ran `php artisan migrate:fresh --seed` in this session → exit code 0, clean from an empty database. `database/seeders/DatabaseSeeder.php` uses `firstOrCreate`/`syncWithoutDetaching` throughout (idempotent). |
| T-07-21 | EoP (demo integrity) — enrollment-driven visibility demo | mitigate | CLOSED | `database/seeders/DatabaseSeeder.php:54-59` — `student`/`student2` enrolled (`EnrollmentStatus::Enrolled`) in the first section; `student3` set to `EnrollmentStatus::Withdrawn` in the second section (deliberately not actively enrolled) — the denial demo is live. |
| T-07-22 | Tampering (test-coverage gap) — un-swept lecturer tests | mitigate | CLOSED | `ClassroomControllerTest.php`, `ClassroomRosterTest.php`, `ClassroomSubjectLinkageTest.php` confirmed absent from disk. Full suite run (187 passed) includes the swept `Grading`/`Lecturer` suites with zero `Classroom`/`classroom_id` references (grepped `tests/Feature/Lecturer/` and `tests/Feature/Grading/` — no matches). |

### Plan 07-08 — Full suite gate, student test sweep

| Threat ID | Category | Disposition | Verdict | Evidence |
|---|---|---|---|---|
| T-07-23 | EoP/Info Disclosure — ENR-08 gate at phase close | mitigate | CLOSED | `php artisan test --filter=ExamVisibilityRegressionTest` re-run live in this session → 4/4 pass, all four enrollment states. |
| T-07-24 | Availability/Tampering — clean-clone boot | mitigate | CLOSED | `php artisan migrate:fresh --seed` (exit 0) + `php artisan test` full suite (187 passed, 488 assertions, 0 failures) — both re-run live in this session, not taken from the report. |
| T-07-25 | Tampering (incomplete sweep) — student-side tests | mitigate | CLOSED | Grepped `tests/Feature/Student/` for `Classroom`/`classroom_id`/`->classrooms(`/`exam_classroom` — zero matches. Full suite green includes all 9 swept student test files. |

---

## Accepted Risks Log

The following threats carry an `accept` disposition per the phase's threat model. Each is logged here with its rationale, closing the verification loop for `accept`-disposition threats.

| Threat ID | Risk | Rationale for Acceptance | Owner |
|---|---|---|---|
| T-07-02 | Dark-mode preference stored in `localStorage`, readable/writable by any script in the page origin | Non-sensitive, client-only UI preference. No server round-trip, no PII, no auth/session impact. Worst case: a user's theme choice is altered — cosmetic only. | Phase 7 (07-01) |
| T-07-SC-view | Alpine `answeredCount` is client-side DOM/JS state, tamperable via devtools | Informational display only (submit-confirmation modal copy). Never gates the Submit button (gated only by `autoSubmitting`) and never substitutes for server-side grading/finalization, which independently re-verifies attempt state on every write. A tampered count cannot bypass any control or alter scoring. | Phase 7 (07-01) |
| T-07-09 | `Section.name` is a computed accessor, not a stored/indexed column | Deliberate design choice (mirrors an existing "live accessor over denormalized column" precedent in this codebase) to eliminate an entire drift/tamper class rather than mitigate it — there is no redundant stored value that could ever diverge from `year`/`semester`/`sequence`. | Phase 7 (07-03) |
| T-07-16 | Dark-mode toggle state in the navbar is client-only | Identical rationale to T-07-02 — no sensitive data, no server trust placed in this value. | Phase 7 (07-05) |

---

## Mass Assignment — Cross-Cutting Verification

Per the audit brief's focus area 4, independently verified beyond the threat register:

- **User.role**: `app/Models/User.php:28-33` includes `role` in `$fillable`, but the *only* controller-facing write path is `app/Http/Controllers/Auth/RegisteredUserController.php:40-48`, which hardcodes `'role' => Role::Student` — never `$request->role`. No other controller in `app/Http/Controllers/` calls `User::create()`/`update()` with request-sourced role data (grepped `User::create\(|->update\(` across `app/Http/Controllers/` — only the registration path and seeder/factory writes found).
- **Enrollment status**: No route in `routes/student.php` or `routes/lecturer.php` writes to the `enrollments` table (grepped `routes/` for `Enrollment` — zero matches). All enrollment writes originate from `database/seeders/DatabaseSeeder.php` and `database/factories/`. Student self-enrollment is explicitly out of scope for Phase 7 (deferred to Phase 8 per the PLAN.md docs).
- **Section.$fillable**: `app/Models/Section.php:16-24` — `subject_id, year, semester, sequence, capacity, opens_at, closes_at`. `sequence` is never taken from request input in `SectionController::store()` — it is computed server-side (`app/Http/Controllers/Lecturer/SectionController.php:58-63`, wrapped in `DB::transaction` + `lockForUpdate()` since the WR-03 fix) and merged into the create array, overriding anything the request might have supplied for that key.
- **Subject.$fillable**: `app/Models/Subject.php:16` — `['name', 'code']` only; no ownership/role field is fillable on this model.

---

## Auxiliary Review Findings (not threat-model items, verified fixed incidentally)

`07-REVIEW.md` raised two additional reliability findings alongside CR-01, both fixed per `07-REVIEW-FIX.md` and independently confirmed in this audit:

- **WR-02** (unhandled 500 on a colliding `year`/`semester`/`sequence` edit): `app/Http/Requests/Lecturer/UpdateSectionRequest.php:45-65` now carries a `Rule::unique('sections')->where(...)->ignore(...)` guard — confirmed present; `SectionControllerTest` "editing a section into a colliding year semester returns a validation error" passes live.
- **WR-03** (non-atomic sequence auto-increment race): `app/Http/Controllers/Lecturer/SectionController.php:56-73` now wraps the `max('sequence')` read and the `Section::create()` in `DB::transaction(...)` with `->lockForUpdate()` — confirmed present.
- **IN-01** (N+1 on the section-assignment checkbox list): resolved as a side effect of the CR-01 fix (`Section::with('subject')` in `ExamController::show()`).

## Unregistered Flags

None. All eight `07-*-SUMMARY.md` files were checked for a `## Threat Flags` section (or any mention of "threat") — none exists. No new attack surface was flagged by any plan executor beyond what the code review (`07-REVIEW.md`) independently surfaced, and that review's findings are fully accounted for above (CR-01 mapped to T-07-12; WR-01 mapped to T-07-10; WR-04 is a UI-consistency fix with no security disposition, verified present in `resources/views/student/attempts/show.blade.php` — `dark:` variants now applied throughout).

---

_Verified: 2026-07-16_
_Verifier: Claude (gsd-secure-phase)_
_Implementation files: read-only, not modified by this audit._
