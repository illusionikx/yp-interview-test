<x-landing-layout>
    <div class="flex min-h-full flex-1 flex-col items-center justify-center px-4 py-16 text-center sm:py-24">
        <h1 class="text-4xl font-semibold leading-tight tracking-tight text-heading">
            {{ __('Online Examination Portal') }}
        </h1>

        <p class="mt-2 text-xl font-semibold leading-tight text-fg-brand">
            {{ __('for Yayasan Peneraju Technical Assessment') }}
        </p>

        <p class="mt-6 max-w-xl text-base font-normal leading-relaxed text-body">
            {{ __('Take timed exams, view results, and manage assessments — all in one place.') }}
        </p>

        <a
            href="{{ route('login') }}"
            class="mt-8 inline-flex items-center justify-center rounded-base bg-brand px-6 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-brand-strong focus:outline-none focus:ring-4 focus:ring-brand-medium"
        >
            {{ __('Sign in') }}
        </a>
    </div>
</x-landing-layout>
