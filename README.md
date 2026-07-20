# Online Examination Portal

> Technical assessment submission for the **Developer** position at **Yayasan Peneraju Pendidikan Bumiputera**, 2026.

A web portal for online examinations and student management, built on **Laravel 11 + Breeze**, **MySQL**, and **Blade / Tailwind / Alpine** (no SPA).

- **Lecturer** — authors and publishes a subject's exams (multiple-choice + open-text) from a per-subject hub, manages its classes, and grades open-text answers. Publishing an exam makes it visible to everyone enrolled in the subject — there is no per-class assignment step.
- **Student** — enrolls in a class, sees only their subjects' exams, takes them under a server-enforced timer, and views the graded result.

MCQs are auto-graded on submit; open-text answers await the lecturer.

## Setup

```bash
git clone <repository-url> && cd yp-test
composer install && npm install
cp .env.example .env && php artisan key:generate
```

Create the MySQL database named `yp-student-exam`, then set the connection in `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yp-student-exam
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate:fresh --seed
npm run build          # or `npm run dev` for hot-reloading
php artisan serve
```

The app runs at `http://localhost:8000`.

## Demo Accounts

Seeded by `migrate:fresh --seed`. Every account's password is **`password`** — evaluation-only, not a production posture.

| Email | Role | State |
|---|---|---|
| `lecturer@example.com` | Lecturer | Manages Mathematics and its "Mathematics Midterm" exam |
| `student@example.com` | Student | Enrolled; **no attempt yet** — take the exam fresh |
| `student2@example.com` | Student | Enrolled; **submitted attempt awaiting open-text grading** |
| `student3@example.com` | Student | **Withdrawn** enrollment — demonstrates status-gated access |

The seeder also fills the app with browsable data: more lecturers and students, five further subjects with their own classes and exams, and a past-semester subject with a fully-graded class — covering every enrollment, attempt, and exam-availability state.

## Walkthrough

**Lecturer** — open a subject to reach its hub → manage its **Classes** and **Exams** tabs → author and **publish** an exam → grade `student2`'s pending open-text answer on the exam's results page.

**Student** — sign in → enroll in a class → start an exam under the live countdown → submit → view the result once grading completes.

## Tests

```bash
php artisan test        # 460 tests
```

> Uses `RefreshDatabase` against the `.env` database, so it **wipes the seeded demo data**. Point it at a disposable database, or re-run `migrate:fresh --seed` afterward.

### Browser tests (Laravel Dusk)

Dusk drives the student and lecturer flows through real navigation in Chrome. It needs its **own database** — its `DatabaseTruncation` must never hit `yp-student-exam`.

1. Create a second database, e.g. `yp-student-exam-dusk`.
2. Copy `.env` to `.env.dusk.local` (git-ignored) and override `DB_DATABASE=yp-student-exam-dusk` plus `APP_URL=http://yp-test.test` — the hostname where Herd serves this project. Dusk drives a real HTTP server, not `php artisan serve`.
3. With Herd serving the app: `php artisan dusk`

ChromeDriver ships bundled under `vendor/laravel/dusk/bin/`. Both browser tests seed their own fixtures.

> **Not automated:** the browser's native "leave this page?" prompt when navigating away mid-exam — ChromeDriver auto-dismisses it before any test can observe it. Check it by hand.

## Deployment

Requires PHP 8.2+, Composer, Node (build-time only), MySQL 8, and a web server with its document root at **`public/`** — never the project root.

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env && php artisan key:generate
```

Set in `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, and the DB credentials. `APP_DEBUG=false` matters — with it on, an uncaught exception renders a stack trace containing environment values to whoever triggered it.

```bash
php artisan migrate --force        # --force: production migrations prompt otherwise
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Make `storage/` and `bootstrap/cache/` writable by the web-server user. Repeat the install/build/migrate/cache steps on each deploy.

> **Never run `db:seed` in production** — the demo accounts above use a publicly documented password.

No queue worker or cron entry is needed: expired attempts finalize lazily on the next request that touches them (see `AttemptGrader`), by design.
