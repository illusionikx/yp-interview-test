# Phase 2: Classroom, Subject & Exam Authoring - Discussion Log

> **Audit trail only.** Decisions are captured in CONTEXT.md.

**Date:** 2026-07-15
**Phase:** 2-Classroom, Subject & Exam Authoring
**Mode:** `--auto` (Claude auto-selected recommended, Phase-1-grounded options; no interactive prompts)
**Areas:** Controller org, Classroom↔Subject linkage, Student roster, Exam authoring UX, MCQ constraints, Publish/edit lock, Lecturer ownership, UI approach

| Area | Auto choice | Rationale |
|------|-------------|-----------|
| Controller org | Resource controllers under `Lecturer\` namespace in `routes/lecturer.php` | Reuses Phase-1 role-gated group |
| Classroom↔Subject | Multi-select on classroom form → `classroom_subject` sync | Natural place for the pivot |
| Student roster | Assign on classroom page (sets `users.classroom_id`) | One class per student (Phase 1 model) |
| Exam authoring UX | Exam page lists questions; inline add-question form, Alpine option rows | Keeps authoring in one place |
| MCQ constraints | ≥2 options, exactly 1 correct, single-select, points≥1 (Form Request) | Matches REQUIREMENTS; multi-select is v2 |
| Publish/edit lock | Unpublished=editable; publish=assignable; unpublish→edit allowed (no attempts yet) | EXM-05/06; revisit lock in Phase 4 |
| Lecturer ownership | Shared management among lecturers (no per-owner policy) | Brief has one Lecturer role; MVP simplicity |
| UI approach | Reuse Breeze/Tailwind/Alpine components; plan `--skip-ui` | No bespoke design; roadmap reserves UI-SPEC for Phases 4/5 |

## Deferred Ideas
- Exam→classroom assignment + student access (ASN/RBAC-05) → Phase 3
- Taking exams/attempts → Phase 4; Grading → Phase 5
- Randomized order, multi-select MCQ → v2
