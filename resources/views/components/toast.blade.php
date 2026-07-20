@php
    // This component is the app's one alert style (UX-02), reading the app's real, existing
    // flash convention (UX-03) directly. It takes no props: per 09-CONTEXT.md's "zero controllers"
    // constraint it reads the session itself, so there is no @props() block to declare — this
    // is the one documented deviation from the status-pill.blade.php @props()-first shape.
    //
    // Key correction, recorded so nobody "fixes" it back: the app's actual convention (verified
    // by grep across app/Http/Controllers — 57 call sites) is the `status` key. The `success`
    // key is used at zero call sites anywhere in this codebase. Read `status` + `error`.
    $sentinels = ['verification-link-sent', 'password-updated', 'profile-updated'];

    // Sentinel exclusion — the landmine. Three shipped Breeze views test the status flash for
    // exact equality against one of the three literal values above and render their own inline
    // confirmation text: auth/verify-email.blade.php:6,
    // profile/partials/update-password-form.blade.php:37, and
    // profile/partials/update-profile-information-form.blade.php:41,53. Toasting one of these
    // would render the raw literal value as a garbled bubble next to the existing "Saved."
    // confirmation those views already show. Exact-equality match only — never a substring or
    // pattern match — so an unrelated real message never gets accidentally swallowed by this
    // list.
    $status = session('status');
    $showStatus = $status && ! in_array($status, $sentinels, true);
    $error = session('error');
@endphp

@if ($showStatus || $error)
    <div
        x-data="{ showStatus: @js((bool) $showStatus), showError: @js((bool) $error) }"
        x-init="setTimeout(() => { showStatus = false }, 4000)"
        class="fixed top-20 right-4 z-50 flex flex-col gap-2"
    >
        @if ($showStatus)
            {{-- Success/info variant — auto-dismisses after ~4s (the x-init timer above), but
                 still always renders a manual close button. --}}
            <div
                x-show="showStatus"
                x-transition
                role="alert"
                class="flex items-start gap-2 w-full max-w-xs p-4 bg-neutral-primary-soft text-body border border-default border-l-4 border-l-green-400 dark:border-l-green-500 rounded-base shadow-xs"
            >
                <svg class="shrink-0 w-5 h-5 text-green-500 dark:text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>

                {{-- Escaping echo only (T-09-02) — flash text is never trusted to be safe HTML,
                     even though every current caller flashes a __()-wrapped literal. --}}
                <div class="text-sm font-normal flex-1">{{ $status }}</div>

                <button
                    type="button"
                    @click="showStatus = false"
                    aria-label="Dismiss"
                    class="shrink-0 inline-flex items-center justify-center rounded-base p-1 text-gray-400 hover:bg-neutral-secondary-medium hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        @if ($error)
            {{-- Error variant — no timer targets this toast. An error the user misses is
                 exactly the FIX-03 bug this component exists to fix; auto-hiding it would
                 re-create that bug, so this is deliberate, not an oversight. It persists until
                 the manual close button below is clicked. --}}
            <div
                x-show="showError"
                x-transition
                role="alert"
                class="flex items-start gap-2 w-full max-w-xs p-4 bg-neutral-primary-soft text-body border border-default border-l-4 border-l-red-400 dark:border-l-red-600 rounded-base shadow-xs"
            >
                <svg class="shrink-0 w-5 h-5 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>

                {{-- Escaping echo only (T-09-02). --}}
                <div class="text-sm font-normal flex-1">{{ $error }}</div>

                <button
                    type="button"
                    @click="showError = false"
                    aria-label="Dismiss"
                    class="shrink-0 inline-flex items-center justify-center rounded-base p-1 text-gray-400 hover:bg-neutral-secondary-medium hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif
    </div>
@endif
