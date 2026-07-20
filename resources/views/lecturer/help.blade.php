<x-app-layout>
    {{--
        DEL-06 — wiki-style lecturer manual (14-02-PLAN.md). Replaces the
        stale v2.0 linear manual (Phase 8's "Sections"/"View Results"/
        "Proceed" copy, superseded by the phases-11–13 Classes/Exams hub
        and two-tab exam editor). A topic index (left rail on lg+, a plain
        list on mobile) plus cross-links between topics; every quoted
        button/tab/link/pill label is verified verbatim against the
        shipped views — see 14-02-SUMMARY.md's accuracy evidence table for
        file:line citations.
    --}}
    <x-slot name="header">
        <h2 class="font-semibold text-3xl text-gray-800 dark:text-white leading-tight">
            {{ __('Lecturer Manual') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="lg:flex lg:items-start lg:gap-6">
                <nav aria-label="{{ __('Manual topics') }}" class="mb-6 lg:mb-0 lg:w-64 lg:shrink-0 lg:sticky lg:top-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ __('Topics') }}</h3>
                        <ol class="space-y-1 text-sm">
                            <li><a href="#topic-home" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('1. Home & dashboard') }}</a></li>
                            <li><a href="#topic-subjects" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('2. Managing subjects') }}</a></li>
                            <li><a href="#topic-classes-tab" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('3. Class management (Classes tab)') }}</a></li>
                            <li><a href="#topic-exams-tab" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('4. Managing exams (Exams tab)') }}</a></li>
                            <li><a href="#topic-exam-editor" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('5. The exam editor') }}</a></li>
                            <li><a href="#topic-grading" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('6. Grading') }}</a></li>
                        </ol>
                    </div>
                </nav>

                <div class="flex-1 min-w-0 space-y-8">

                    <section id="topic-home" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Home & dashboard') }}</h3>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Your dashboard, the Lecturer area, shows three tiles: "Classes this & future semesters", "Students enrolled / seats", and "Attempts awaiting grading".') }}
                        </p>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Below, "Your subjects" lists every subject you\'re assigned to, with columns "Code", "Name", "#Classes", "#Exams". Click "New subject" to create one, or a row\'s "Manage" link to open it — see') }}
                            <a href="#topic-subjects" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Managing subjects') }}</a>.
                        </p>
                    </section>

                    <section id="topic-subjects" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Managing subjects') }}</h3>
                        <ol class="list-decimal list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('From your dashboard, click "New subject", fill in "Name" and an optional "Code (optional)", then click "Create subject".') }}</li>
                            <li>{{ __('A row\'s "Manage" link opens the subject\'s two-tab hub — see') }}
                                <a href="#topic-classes-tab" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Class management (Classes tab)') }}</a>
                                {{ __('and') }}
                                <a href="#topic-exams-tab" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Managing exams (Exams tab)') }}</a>.</li>
                            <li>{{ __('A row\'s "Edit" link opens the subject\'s edit page, where "Assigned Lecturers" lists every lecturer who can manage the subject. Pick another lecturer under "Assign a lecturer" and click "Assign Lecturer" — any assigned lecturer can manage that subject\'s classes and exams, not just whoever created it.') }}</li>
                            <li>{{ __('Click a row\'s "Delete" to remove a subject; a subject that still has exams or classes cannot be deleted until those are removed first.') }}</li>
                        </ol>
                    </section>

                    <section id="topic-classes-tab" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Class management (Classes tab)') }}</h3>
                        <ol class="list-decimal list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('Open a subject via "Manage" from your dashboard, then select the "Classes" tab (the hub defaults to it).') }}</li>
                            <li>{{ __('Click "Create class" to open enrollment for a new class.') }}</li>
                            <li>{{ __('Classes are grouped by semester in a table with columns "Class code", "Students", "Status" — the Status pill reads "Open", "Opens", or "Closed".') }}</li>
                            <li>{{ __('Each class\'s "Students" column shows a progress bar with "{enrolled} / {capacity}" beneath it.') }}</li>
                            <li>{{ __('Click "View roster" to see everyone enrolled, "Edit" to change the class\'s settings, or "Delete" to remove it.') }}</li>
                            <li>{{ __('Past semesters are collapsed by default under "Show past semesters" (toggles to "Hide past semesters").') }}</li>
                        </ol>
                    </section>

                    <section id="topic-exams-tab" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Managing exams (Exams tab)') }}</h3>
                        <ol class="list-decimal list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('On the same subject hub, select the "Exams" tab. Click "New exam" to create one.') }}</li>
                            <li>{{ __('Each row shows the exam "Title", a "Status" pill ("Published" or "Draft") with an inline "Publish"/"Unpublish" toggle button, and "Grading" progress ("{graded} / {total} graded" with a "Grade" link, or "No attempts yet").') }}</li>
                            <li>{{ __('Click a row\'s "Edit" to open') }}
                                <a href="#topic-exam-editor" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('the exam editor') }}</a>.</li>
                            <li>{{ __('Click "Reset submissions" to permanently delete every attempt on that exam so students can retake it — the button is disabled until at least one attempt exists. A confirmation titled "Reset exam submissions?" explains exactly how many attempts (and how many already-graded scores) will be lost before you confirm with "Reset {count} submissions".') }}</li>
                            <li>{{ __('A draft exam can also be deleted entirely via "Delete"; a published exam cannot.') }}</li>
                        </ol>
                    </section>

                    <section id="topic-exam-editor" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('The exam editor') }}</h3>
                        <ol class="list-decimal list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('Open an exam via "Edit" (from the Exams tab) to reach its two-tab editor: "Details" and "Questions". Both tabs, and every action inside them, stay editable regardless of whether the exam is published.') }}</li>
                            <li>{{ __('On "Details", set "Subject", "Exam / test name", an optional "Description (optional)", "Duration (minutes)", and the optional "Available from (optional)" / "Available until (optional)" window, then click "Save changes".') }}</li>
                            <li>{{ __('The same page\'s "Submissions" panel shows how many students have started the exam and offers the same "Reset submissions" action described in') }}
                                <a href="#topic-exams-tab" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Managing exams (Exams tab)') }}</a>.</li>
                            <li>{{ __('On "Questions", each question shows its position as "Q{number}" with "Move question up" / "Move question down" buttons beside it, its type ("Multiple choice" or "Open text"), and "Edit" / "Delete" actions. For multiple choice, each option can be reordered with "Move option up" / "Move option down", and "Shuffle options" randomizes the option order once.') }}</li>
                            <li>{{ __('Use "Add a question" at the bottom of the Questions tab to create a new one: choose the "Question type", enter "Question text" and "Points", and for multiple choice fill in each option\'s text (with "Add option" / "Remove") and mark the correct one, then click "Add question" (or "Save question" when editing).') }}</li>
                            <li>{{ __('Publish the exam from the Exams tab or the editor\'s header once ready — students in an assigned class only see it once published. See') }}
                                <a href="#topic-grading" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Grading') }}</a>
                                {{ __('once students start submitting.') }}</li>
                        </ol>
                    </section>

                    <section id="topic-grading" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Grading') }}</h3>
                        <ol class="list-decimal list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('Open an exam\'s results from "View Results" (in the exam editor) or "Grade" (from the Exams tab or dashboard) to reach its results list, showing each student\'s "Status" ("Submitted" or "Graded") and "Score", with a "Grading progress" summary at the top.') }}</li>
                            <li>{{ __('Multiple-choice questions are graded automatically the instant a student submits — you never need to score those; each shows "Auto-graded" plus "✓ Correct" or "✗ Incorrect" (with "Correct answer: {text}" shown when wrong).') }}</li>
                            <li>{{ __('Click a row\'s "Grade" (or "View", once already graded) to open the attempt\'s breakdown.') }}</li>
                            <li>{{ __('For each open-text question, enter a "Score" and click "Save Score" — you can reopen it any time via "Edit".') }}</li>
                            <li>{{ __('A student\'s result stays hidden — showing "Awaiting grading" on their side — until every open-text answer on their attempt has a score.') }}</li>
                        </ol>
                    </section>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
