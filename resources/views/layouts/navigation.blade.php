<nav x-data="{ mobileOpen: false }" class="bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Wordmark (UI-01/UI-02: text wordmark replaces the logo in the navbar slot) -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ __('Online Examination Portal') }}
                    </a>
                </div>

                <!--
                    Role-scoped navigation links (NAV-03 trim).

                    These are INTERIM utility links, not the primary navigation anymore: the
                    drill-down hierarchy (home = dashboard + subject list) is now the primary
                    path. They stay here only so Sections/Exams/Results (lecturer) and
                    Class enrollment/My Exams (student) are not orphaned (NAV-04) until Phase 12
                    (subject & class management hub) and Phase 13 (student class page) absorb
                    them into the drill-down hierarchy. Retire this block once those phases ship.
                -->
                <div class="hidden sm:ms-10 sm:flex sm:items-center sm:space-x-6">
                    @if (auth()->user()->isLecturer())
                        <a href="{{ route('lecturer.sections.index') }}"
                           class="text-sm font-semibold {{ request()->routeIs('lecturer.sections.*') ? 'text-blue-600 dark:text-blue-500' : 'text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-500' }}">
                            {{ __('Classes') }}
                        </a>
                        {{-- CLS-04 (12-04): the interim "Exams" link retired — the unscoped
                             listing it pointed to is folded into each subject's Exams tab
                             (lecturer.home -> subjects.manage?tab=exams), so there is no
                             longer a single all-exams destination to link to here. --}}
                    @else
                        <a href="{{ route('student.exams.index') }}"
                           class="text-sm font-semibold {{ request()->routeIs('student.exams.*') ? 'text-blue-600 dark:text-blue-500' : 'text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-500' }}">
                            {{ __('My Exams') }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-2">
                <!-- Dark-mode toggle (UI-02) — mirrors 07-01's pre-paint bootstrap script -->
                <button
                    type="button"
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    x-on:click="
                        dark = ! dark;
                        document.documentElement.classList.toggle('dark', dark);
                        localStorage.setItem('theme', dark ? 'dark' : 'light');
                    "
                    aria-label="{{ __('Toggle dark mode') }}"
                    class="inline-flex items-center justify-center rounded-lg p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                >
                    <svg x-show="! dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                    <svg x-show="dark" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                </button>

                {{-- UX-05: Help button, an immediate sibling of the theme toggle so both
                     utility controls sit together. Opens the role's wiki-style manual
                     (DEL-06). Retires the interim "Help" nav link above. --}}
                <a
                    href="{{ auth()->user()->isLecturer() ? route('lecturer.help.show') : route('student.help.show') }}"
                    aria-label="{{ __('Help') }}"
                    class="inline-flex items-center justify-center rounded-lg p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 17.25h.007v.008H12v-.008Z" />
                    </svg>
                    <span class="sr-only">{{ __('Help') }}</span>
                </a>

                <!-- User dropdown -->
                <x-dropdown align="right" width="48" content-classes="py-1 bg-white dark:bg-gray-700">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition ease-in-out duration-150 dark:text-gray-300 dark:bg-gray-800 dark:hover:text-white">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')" class="dark:text-gray-200 dark:hover:bg-gray-600">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')" class="dark:text-gray-200 dark:hover:bg-gray-600"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden gap-1">
                <button
                    type="button"
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    x-on:click="
                        dark = ! dark;
                        document.documentElement.classList.toggle('dark', dark);
                        localStorage.setItem('theme', dark ? 'dark' : 'light');
                    "
                    aria-label="{{ __('Toggle dark mode') }}"
                    class="inline-flex items-center justify-center rounded-lg p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                >
                    <svg x-show="! dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                    <svg x-show="dark" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                </button>

                {{-- UX-05: Help button beside the mobile theme toggle, mirroring the
                     desktop pairing above. --}}
                <a
                    href="{{ auth()->user()->isLecturer() ? route('lecturer.help.show') : route('student.help.show') }}"
                    aria-label="{{ __('Help') }}"
                    class="inline-flex items-center justify-center rounded-lg p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 17.25h.007v.008H12v-.008Z" />
                    </svg>
                    <span class="sr-only">{{ __('Help') }}</span>
                </a>

                <button @click="mobileOpen = ! mobileOpen" aria-label="{{ __('Toggle navigation menu') }}" class="inline-flex items-center justify-center p-2.5 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': mobileOpen, 'inline-flex': ! mobileOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! mobileOpen, 'inline-flex': mobileOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': mobileOpen, 'hidden': ! mobileOpen}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            {{-- Interim utility links (NAV-03/NAV-04) — see the desktop block's comment above. --}}
            @if (auth()->user()->isLecturer())
                <x-responsive-nav-link :href="route('lecturer.sections.index')" :active="request()->routeIs('lecturer.sections.*')" class="dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white">
                    {{ __('Classes') }}
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link :href="route('student.exams.index')" :active="request()->routeIs('student.exams.*')" class="dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white">
                    {{ __('My Exams') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')" class="dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')" class="dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
