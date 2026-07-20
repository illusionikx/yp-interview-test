<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                {{ $section->subject->name }} — {{ $section->name }}
            </h2>
            <x-back-button :href="route('lecturer.subjects.manage', $section->subject).'?tab=classes'">{{ __('Back to classes') }}</x-back-button>
        </div>
    </x-slot>

    {{-- Class page: a Students (roster) tab and a Settings tab (the class edit
         form + delete, moved here off the class list). Defaults to Settings when
         a save failed so the errors are visible. --}}
    <div class="py-12" x-data="{ tab: '{{ $errors->any() ? 'settings' : 'students' }}' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-6">
                    <button type="button" @click="tab = 'students'"
                        :class="tab === 'students' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold">
                        {{ __('Students') }}
                    </button>
                    <button type="button" @click="tab = 'settings'"
                        :class="tab === 'settings' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-semibold">
                        {{ __('Settings') }}
                    </button>
                </nav>
            </div>

            <div x-show="tab === 'students'">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                    @forelse ($students as $student)
                        @if ($loop->first)
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Student') }}</th>
                                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Enrolled Since') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @endif
                                    <tr>
                                        <td class="px-4 py-2 text-sm">
                                            {{-- Click the name for details (issue). --}}
                                            <button type="button" x-data @click="$dispatch('open-modal', 'student-{{ $student->id }}')" class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400 hover:underline">{{ $student->name }}</button>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $student->pivot->created_at->format('M j, Y') }}</td>
                                    </tr>
                        @if ($loop->last)
                                </tbody>
                            </table>
                        @endif
                    @empty
                        <div class="text-center">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('No students enrolled') }}</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Students who apply to this class will appear here.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div x-show="tab === 'settings'" x-cloak>
                @include('lecturer.sections._settings', ['subject' => $section->subject, 'section' => $section])
            </div>
        </div>
    </div>

    {{-- Per-student details modal (opened by clicking a name), carrying the
         reject-enrollment action. --}}
    @foreach ($students as $student)
        <x-modal name="student-{{ $student->id }}" focusable>
            <div class="p-6" x-data="{ reason: '' }">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $student->name }}</h2>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Email') }}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $student->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Enrolled since') }}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $student->pivot->created_at->format('M j, Y') }}</dd>
                    </div>
                </dl>

                <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Reject enrollment') }}</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __(':student will be removed from this class and can see the reason you select. They may re-apply while the enrollment window is still open.', ['student' => $student->name]) }}</p>

                    <form method="POST" action="{{ route('lecturer.sections.enrollments.reject', [$section, $student]) }}" class="mt-3">
                        @csrf
                        @method('PATCH')

                        <x-input-label for="reason-{{ $student->id }}" :value="__('Reason')" class="dark:text-gray-300" />
                        <select id="reason-{{ $student->id }}" name="reason" x-model="reason" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500 rounded-md shadow-sm" required>
                            <option value="" disabled selected>{{ __('Select a reason') }}</option>
                            @foreach (\App\Enums\RejectionReason::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>

                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Close') }}</x-secondary-button>
                            <x-danger-button type="submit" x-bind:disabled="! reason" class="disabled:opacity-50 disabled:cursor-not-allowed">{{ __('Reject Student') }}</x-danger-button>
                        </div>
                    </form>
                </div>
            </div>
        </x-modal>
    @endforeach
</x-app-layout>
