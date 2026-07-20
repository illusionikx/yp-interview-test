<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ $subject->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                @forelse ($sections as $section)
                    @if ($loop->first)
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Section') }}</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Capacity') }}</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Enrollment Window') }}</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Your Status / Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @endif
                                @php
                                    $ownEnrollment = $ownEnrollments[$section->id] ?? null;
                                    $isFull = $section->enrolled_total >= $section->capacity;
                                    $windowStatus = $section->windowStatus();
                                    if ($windowStatus === 'opens') {
                                        $windowLabel = __('Opens :date', ['date' => $section->opens_at->format('M j, Y')]);
                                    } elseif ($windowStatus === 'closed') {
                                        $windowLabel = __('Closed');
                                    } else {
                                        $windowLabel = __('Open');
                                    }
                                    $sectionActiveElsewhere = $activeElsewhere[$section->id] ?? false;
                                    $canApply = $windowStatus === 'open' && ! $isFull && ! $sectionActiveElsewhere;
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $section->name }}</td>
                                    <td class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $section->enrolled_total }}/{{ $section->capacity }}
                                        @if ($isFull)
                                            <x-status-pill status="full">{{ __('FULL') }}</x-status-pill>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-sm"><x-status-pill :status="$windowStatus">{{ $windowLabel }}</x-status-pill></td>
                                    <td class="px-4 py-2 text-sm">
                                        @if ($ownEnrollment && $ownEnrollment->status === \App\Enums\EnrollmentStatus::Enrolled)
                                            <x-status-pill status="enrolled">{{ __('Enrolled') }}</x-status-pill>
                                            <button type="button" x-data @click="$dispatch('open-modal', 'withdraw-section-{{ $section->id }}')" class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('Withdraw') }}</button>
                                        @elseif ($ownEnrollment && $ownEnrollment->status === \App\Enums\EnrollmentStatus::Rejected)
                                            <div class="space-y-1">
                                                <x-status-pill status="rejected">{{ __('Rejected') }}</x-status-pill>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Rejected: :reason', ['reason' => $ownEnrollment->rejection_reason?->label()]) }}</p>
                                                @if ($canApply)
                                                    <form method="POST" action="{{ route('student.sections.enroll', $section) }}">
                                                        @csrf
                                                        <x-primary-button type="submit">{{ __('Apply') }}</x-primary-button>
                                                    </form>
                                                @endif
                                            </div>
                                        @elseif ($ownEnrollment && $ownEnrollment->status === \App\Enums\EnrollmentStatus::Withdrawn)
                                            <div class="space-y-1">
                                                <x-status-pill status="withdrawn">{{ __('Withdrawn') }}</x-status-pill>
                                                @if ($canApply)
                                                    <form method="POST" action="{{ route('student.sections.enroll', $section) }}">
                                                        @csrf
                                                        <x-primary-button type="submit">{{ __('Apply') }}</x-primary-button>
                                                    </form>
                                                @endif
                                            </div>
                                        @elseif ($canApply)
                                            <form method="POST" action="{{ route('student.sections.enroll', $section) }}">
                                                @csrf
                                                <x-primary-button type="submit">{{ __('Apply') }}</x-primary-button>
                                            </form>
                                        @elseif ($sectionActiveElsewhere)
                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Enrolled in another section this semester') }}</p>
                                        @endif
                                    </td>
                                </tr>
                    @if ($loop->last)
                            </tbody>
                        </table>
                    @endif
                @empty
                    <div class="text-center">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('No sections yet') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('This subject has no sections open for enrollment right now.') }}</p>
                    </div>
                @endforelse
            </div>

            @foreach ($sections as $section)
                @php
                    $ownEnrollment = $ownEnrollments[$section->id] ?? null;
                @endphp
                @if ($ownEnrollment && $ownEnrollment->status === \App\Enums\EnrollmentStatus::Enrolled)
                    <x-modal name="withdraw-section-{{ $section->id }}" focusable>
                        <div class="p-6">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Withdraw from Section') }}</h2>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __("You'll lose your seat in :section. You can re-apply later while the enrollment window is still open.", ['section' => $section->name]) }}</p>
                            <div class="mt-6 flex justify-end gap-3">
                                <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                                <form method="POST" action="{{ route('student.sections.withdraw', $section) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button>{{ __('Withdraw') }}</x-danger-button>
                                </form>
                            </div>
                        </div>
                    </x-modal>
                @endif
            @endforeach
        </div>
    </div>
</x-app-layout>
