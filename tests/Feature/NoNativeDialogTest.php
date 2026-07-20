<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * UX-02 static-scan gate: no Blade view may invoke a native browser dialog (confirm()/alert()).
 * Pure file-content assertion — no RefreshDatabase, no HTTP call — the closest style precedent is
 * tests/Unit/WindowSemanticsTest.php, despite this file living under tests/Feature per
 * 09-VALIDATION.md's Wave 0 list.
 *
 * RED as of this plan (09-03): both tests fail today because the 3 known native confirm() call
 * sites still exist. Plan 09-10 migrates them onto <x-confirm-modal> and turns this green.
 */
class NoNativeDialogTest extends TestCase
{
    /**
     * UX-02 requires one popup/alert style throughout the app — the browser's native dialogs
     * (confirm()/alert()) are never used. The three call sites this scan finds today
     * (lecturer/exams/show.blade.php x2, lecturer/subjects/index.blade.php x1) are migrated onto
     * <x-confirm-modal> by plan 09-10.
     */
    public function test_no_blade_view_invokes_a_native_browser_dialog(): void
    {
        // This scan is a blunt substring match, so no Blade file may define an Alpine or
        // JavaScript function whose name ends in the needle text — e.g. an x-data method named
        // for the confirmation action would trip this gate even though it is not a native
        // dialog. Plan 09-11's component must name its Alpine handler something else. The
        // needles live in an array rather than being inlined twice to make this constraint
        // obvious at a glance.
        $needles = ['confirm(', 'alert('];

        $violations = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = file_get_contents($file->getPathname());

            foreach ($needles as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = $file->getRelativePathname();

                    break;
                }
            }
        }

        $this->assertSame([], $violations, 'Native browser dialog found in: '.implode(', ', $violations));
    }

    /**
     * Without this, test 1 above could be satisfied by simply deleting the confirmation step
     * entirely rather than migrating it onto <x-confirm-modal> — a silent removal of a
     * destructive-action guard.
     */
    public function test_the_destructive_lecturer_forms_use_the_confirm_modal_component(): void
    {
        $examsShow = file_get_contents(resource_path('views/lecturer/exams/show.blade.php'));
        $subjectsIndex = file_get_contents(resource_path('views/lecturer/subjects/index.blade.php'));

        $this->assertStringContainsString('<x-confirm-modal', $examsShow);
        $this->assertStringContainsString('<x-confirm-modal', $subjectsIndex);
    }

    /**
     * WR-01 (09-REVIEW.md): pins the x-ref / $refs.<name>.submit() wiring between each
     * destructive form and its paired <x-confirm-modal>. The prior tests above only check
     * that a component tag is present and that no native dialog exists — neither would catch
     * a copy-paste typo that renames a form's x-ref without updating the modal's
     * x-on:click="$refs.<name>.submit()" call (or vice versa), which silently breaks delete:
     * the modal opens, "Delete" does nothing, and every other test in this file stays green.
     *
     * Honest limit: PHPUnit executes no browser JS, so it cannot click the rendered button
     * and observe Alpine actually invoke $refs.<name>.submit(). What IS verifiable from
     * rendered markup, and is exactly the failure mode this finding calls out, is whether the
     * x-ref name a form declares and the $refs.<name> name the modal targets still agree, and
     * whether every destructive form still carries @csrf + @method('DELETE'). That residual
     * "does clicking it in a real browser work" gap is closed by Dusk in Phase 14
     * (TEST-01..04), not here.
     *
     * Phase 12 plan 02 folded the exam-details form (x-ref="editExamForm") into this same
     * file, still guarded by the shared `_save-warning-modal.blade.php` partial (EDT-04) —
     * that partial's `x-on:click` target is the dynamic `$formRef` parameter, not a literal
     * name, so it can never match the literal-name regex below no matter which file includes
     * it. Its pairing is instead proven by the `'formRef' => '...'` argument passed to the
     * `@include()` call site, which is captured separately and merged into $modalRefs.
     */
    public function test_each_destructive_forms_x_ref_matches_its_confirm_modals_refs_submit_call(): void
    {
        foreach ([
            resource_path('views/lecturer/exams/show.blade.php'),
            resource_path('views/lecturer/subjects/index.blade.php'),
        ] as $path) {
            $contents = file_get_contents($path);

            preg_match_all('/x-ref="([a-zA-Z0-9_]+)"/', $contents, $formRefMatches);
            preg_match_all('/x-on:click="\$refs\.([a-zA-Z0-9_]+)\.submit\(\)"/', $contents, $modalRefMatches);
            preg_match_all(
                "/@include\('lecturer\.exams\._save-warning-modal',\s*\[[^\]]*'formRef'\s*=>\s*'([a-zA-Z0-9_]+)'/",
                $contents,
                $sharedModalRefMatches
            );

            $formRefs = $formRefMatches[1];
            $modalRefs = array_merge($modalRefMatches[1], $sharedModalRefMatches[1]);

            $this->assertNotEmpty($formRefs, "No destructive form x-ref found in {$path}.");

            sort($formRefs);
            sort($modalRefs);

            $this->assertSame(
                $formRefs,
                $modalRefs,
                'x-ref names on destructive forms and $refs...submit() targets on their confirm '
                ."modals are out of sync in {$path} — a modal would open but its confirm button "
                .'would submit nothing.'
            );

            // Every destructive (DELETE) form must still carry the method-spoof field —
            // losing it would make the confirmed delete a plain GET. Forms paired with the
            // shared EDT-04 `_save-warning-modal` partial (editExamForm et al.) are PUT/POST
            // saves guarded by the same open-modal confirmation pattern, not HTTP DELETEs —
            // exclude those from this count rather than the pairing check above.
            $destructiveFormRefs = array_diff($formRefs, $sharedModalRefMatches[1]);

            $this->assertSame(
                count($destructiveFormRefs),
                substr_count($contents, "@method('DELETE')"),
                "Expected one @method('DELETE') per destructive form in {$path}."
            );
            $this->assertGreaterThanOrEqual(
                count($formRefs),
                substr_count($contents, '@csrf'),
                "Expected at least one @csrf per destructive form in {$path}."
            );
        }
    }
}
