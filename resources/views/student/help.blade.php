<x-app-layout>
    {{--
        DEL-06 — wiki-style student manual (14-02-PLAN.md). Replaces the
        stale v2.0 linear manual (Phase 8's "Enroll"/"View Sections"/
        "Apply"/"My Exams" copy, none of which exist in the shipped
        phases-11–13 UI). A topic index (left rail on lg+, a plain list on
        mobile) plus cross-links between topics; every quoted button/tab/
        link/pill label is verified verbatim against the shipped views —
        see 14-02-SUMMARY.md's accuracy evidence table for file:line
        citations.
    --}}
    <x-slot name="header">
        <h2 class="font-semibold text-3xl text-gray-800 dark:text-white leading-tight">
            {{ __('Student Manual') }}
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
                            <li><a href="#topic-enrolling" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('2. Enrolling in a class') }}</a></li>
                            <li><a href="#topic-class-page" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('3. Your subjects & class page') }}</a></li>
                            <li><a href="#topic-taking-exam" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('4. Taking a timed exam') }}</a></li>
                            <li><a href="#topic-results" class="block rounded-md px-2 py-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-gray-700">{{ __('5. Viewing your results') }}</a></li>
                        </ol>
                    </div>
                </nav>

                <div class="flex-1 min-w-0 space-y-8">

                    <section id="topic-home" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Home & dashboard') }}</h3>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Your dashboard, the Student area, opens with two summary tiles: "Subjects enrolled this semester" and "Exams available to take".') }}
                        </p>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Below them, "Your subjects" lists every class you are enrolled in this semester, grouped by semester. Click "Enroll in a class" to open') }}
                            <a href="#topic-enrolling" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Enrolling in a class') }}</a>.
                        </p>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Each row\'s "Open class page" link takes you to that subject\'s class page — see') }}
                            <a href="#topic-class-page" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Your subjects & class page') }}</a>.
                        </p>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Past semesters are collapsed by default; click "Show past semesters" to reveal them (the button then reads "Hide past semesters").') }}
                        </p>
                    </section>

                    <section id="topic-enrolling" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Enrolling in a class') }}</h3>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('From your dashboard, click "Enroll in a class" to open the "Class enrollment" page.') }}
                        </p>
                        <ol class="list-decimal list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('Step "1. Choose a subject": pick a subject from the Subject dropdown and click "Choose".') }}</li>
                            <li>{{ __('Step "2. Select a class for {subject}" lists every class for that subject in a table with columns "Class", "Capacity", "Enrollment Window", and "Your Status / Action".') }}</li>
                            <li>{{ __('Capacity shows filled/total seats; a full class also shows an amber "FULL" label. The Enrollment Window column shows "Open", "Opens {date}", or "Closed".') }}</li>
                            <li>{{ __('If a class is Open and has room, click "Enroll" to join it immediately — there is no approval step.') }}</li>
                            <li>{{ __('If you\'re already enrolled elsewhere in the same subject this semester, that row shows "Enrolled in another section this semester" instead of an Enroll button.') }}</li>
                            <li>{{ __('Once enrolled, the row shows a green "Enrolled" pill and a "Withdraw" link. Clicking "Withdraw" opens a confirmation titled "Withdraw from Class" — click "Withdraw" again to confirm or "Cancel" to stay enrolled.') }}</li>
                            <li>{{ __('If a lecturer rejects your enrollment, the row shows a red "Rejected" pill with "Rejected: {reason}" underneath; you may click "Enroll" again while the window stays open. A withdrawn class shows a gray "Withdrawn" pill and the same re-enroll option.') }}</li>
                        </ol>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('After enrolling, visit') }}
                            <a href="#topic-class-page" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Your subjects & class page') }}</a>
                            {{ __('to see the class\'s exams.') }}
                        </p>
                    </section>

                    <section id="topic-class-page" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Your subjects & class page') }}</h3>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('From your dashboard\'s "Your subjects" table, click a row\'s "Open class page" link to open that subject\'s class page.') }}
                        </p>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('The page shows the "Subject" (code and name), the "Lecturer", and "Your class" — the class you\'re enrolled in.') }}
                        </p>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Below that, the "Exams" card lists every exam assigned to your class with an availability label — "Available", "Opens {date}", or "Closed" — shown next to the exam title regardless of whether you can start it yet.') }}
                        </p>
                        <ul class="list-disc list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('If you haven\'t started an exam, its row shows a "Start" button — see') }}
                                <a href="#topic-taking-exam" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Taking a timed exam') }}</a>.</li>
                            <li>{{ __('If you\'re mid-attempt, "Start" is disabled and a "Resume" link appears instead.') }}</li>
                            <li>{{ __('Once you\'ve submitted, "Start" stays disabled and a "Taken" (or "Graded", once scored) link appears — see') }}
                                <a href="#topic-results" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Viewing your results') }}</a>.</li>
                        </ul>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Click "Back to your subjects" to return to your dashboard.') }}
                        </p>
                    </section>

                    <section id="topic-taking-exam" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Taking a timed exam') }}</h3>
                        <ol class="list-decimal list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('Click "Start" on a class page\'s exam row to begin. The countdown timer at the top starts the moment you begin and keeps running even if the exam\'s availability window closes while you\'re still working.') }}</li>
                            <li>{{ __('The sticky bar at the top shows the subject name, exam title, the live timer, and "{answered} of {total} answered" progress. Click "Instructions" to open the exam-details popup, which shows "Subject", "Duration", "Questions", and an "Instructions" list.') }}</li>
                            <li>{{ __('For multiple-choice questions, click an option; for open-text questions, type your answer and click outside the box. Each answer saves automatically — you\'ll see "Saving…" and then a green "Saved" mark beside it. If a save fails, click "Save failed — Retry".') }}</li>
                            <li>{{ __('A one-shot toast reading "10 minutes remaining." appears once, ten minutes before time runs out.') }}</li>
                            <li>{{ __('When ready, click "Submit Exam". A confirmation titled "Submit this exam?" shows "You won\'t be able to change your answers after this." along with how many questions you\'ve answered — click "Yes, Submit" to finish or "Keep Working" to go back.') }}</li>
                            <li>{{ __('If the timer reaches zero before you submit, a banner reading "Time\'s up — submitting your exam…" appears and the exam is submitted for you automatically.') }}</li>
                        </ol>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('After submitting, see') }}
                            <a href="#topic-results" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('Viewing your results') }}</a>.
                        </p>
                    </section>

                    <section id="topic-results" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6 space-y-3 scroll-mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Viewing your results') }}</h3>
                        <ol class="list-decimal list-inside space-y-2 text-base text-gray-700 dark:text-gray-300">
                            <li>{{ __('Once you\'ve submitted an exam, its row on the') }}
                                <a href="#topic-class-page" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 underline">{{ __('class page') }}</a>
                                {{ __('shows a "Taken" or "Graded" link — click it to open your result.') }}</li>
                            <li>{{ __('Multiple-choice questions are graded automatically the instant you submit.') }}</li>
                            <li>{{ __('Until every open-text answer on your attempt has been graded, the page shows "Awaiting grading" with the note "Your submission has been recorded. Your lecturer still needs to grade one or more open-text answers before your final score is available."') }}</li>
                            <li>{{ __('Once fully graded, the page instead shows "Your Result" with your total written as "{score} / {total} points", followed by each question\'s breakdown — your answer, and for multiple-choice, whether it was marked "✓ Correct" or "✗ Incorrect".') }}</li>
                        </ol>
                        <p class="text-base text-gray-700 dark:text-gray-300">
                            {{ __('Click "Back to my exams" to return.') }}
                        </p>
                    </section>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
