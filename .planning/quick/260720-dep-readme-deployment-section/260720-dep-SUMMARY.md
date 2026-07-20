---
quick_id: 260720-dep
title: README — add a "Deploying to a Server" section
date: 2026-07-20
status: complete
tests: not run (documentation only; no code touched)
build: not required
---

# Quick Task 260720-dep — README deployment section

## Why
The README covered setup from a clean clone for *local review* only (`php
artisan serve`, `migrate:fresh --seed`, demo credentials). It said nothing
about running the app on a real server, which is the other half of what a
reader delivering this repo needs.

## What changed
One new `## Deploying to a Server` section in `README.md`, placed between
"Running the Browser Tests" and "Publishing to GitHub". Covers:

- server requirements (PHP 8.2+, Composer, Node at build time, MySQL 8,
  document root at `public/` not the project root)
- `composer install --no-dev --optimize-autoloader` + `npm ci && npm run build`
- production `.env` keys, with a note on why `APP_DEBUG=false` matters
  (stack traces leak environment values to whoever triggers the exception)
- `migrate --force`, `storage:link`, `config:cache`/`route:cache`/`view:cache`
- writable `storage/` and `bootstrap/cache/`
- an explicit **do not seed in production** warning — the seeder creates demo
  accounts with the publicly-documented password `password`
- a closing note that no queue worker or cron entry is needed, because expired
  attempts finalize lazily on the next touching request (matches the actual
  design decision in `AttemptGrader`, not an aspiration)

## Deliberately not included
No Docker/Forge/Vapor/CI-pipeline recipes, no nginx vhost config, no
zero-downtime deploy dance. This is a graded assessment deliverable, not a
product with an ops story — a reader who deploys to their own stack knows
their own web server. Add a platform-specific recipe if a target platform is
ever actually chosen.
