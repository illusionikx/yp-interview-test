<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Edit subject') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('lecturer.subjects.update', $subject) }}">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" :value="__('Name')" class="dark:text-gray-300" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('name', $subject->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="code" :value="__('Code (optional)')" class="dark:text-gray-300" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500" :value="old('code', $subject->code)" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-6 gap-3">
                        <x-back-button :href="route('lecturer.home')">{{ __('Back to subjects') }}</x-back-button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            {{ __('Save Changes') }}
                        </button>
                    </div>
                </form>
            </div>

            @php
                $assignedLecturers = $subject->lecturers()->orderBy('name')->get();
                $assignableLecturers = \App\Models\User::where('role', \App\Enums\Role::Lecturer)
                    ->whereNotIn('id', $assignedLecturers->pluck('id'))
                    ->orderBy('name')
                    ->get();
            @endphp
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">{{ __('Assigned Lecturers') }}</h3>

                @if ($assignedLecturers->isEmpty())
                    <div class="mb-6">
                        <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">{{ __('No lecturers assigned') }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __("Assign a lecturer so they can manage this subject's sections and exams.") }}</p>
                    </div>
                @else
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700 mb-6">
                        @foreach ($assignedLecturers as $lecturer)
                            <li class="py-2 flex items-center justify-between text-sm">
                                <span class="text-gray-900 dark:text-gray-100">{{ $lecturer->name }} ({{ $lecturer->email }})</span>
                                <button type="button" x-data @click="$dispatch('open-modal', 'unassign-lecturer-{{ $lecturer->id }}')" class="text-red-600 hover:text-red-800 dark:text-red-500 dark:hover:text-red-400">{{ __('Unassign Lecturer') }}</button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($assignableLecturers->isNotEmpty())
                    <form method="POST" action="{{ route('lecturer.subjects.lecturers.store', $subject) }}" class="flex items-end gap-3">
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="user_id" :value="__('Assign a lecturer')" class="dark:text-gray-300" />
                            <select id="user_id" name="user_id" class="mt-1 block w-full border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-blue-500 focus:border-blue-500 rounded-md shadow-sm">
                                @foreach ($assignableLecturers as $lecturer)
                                    <option value="{{ $lecturer->id }}">{{ $lecturer->name }} ({{ $lecturer->email }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            {{ __('Assign Lecturer') }}
                        </button>
                    </form>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No more lecturers available to assign.') }}</p>
                @endif
            </div>

            @foreach ($assignedLecturers as $lecturer)
                <x-modal name="unassign-lecturer-{{ $lecturer->id }}" focusable>
                    <div class="p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Unassign Lecturer') }}</h2>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __(':name will no longer be able to manage this subject\'s sections or exams.', ['name' => $lecturer->name]) }}</p>
                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                            <form method="POST" action="{{ route('lecturer.subjects.lecturers.destroy', [$subject, $lecturer]) }}">
                                @csrf
                                @method('DELETE')
                                <x-danger-button>{{ __('Unassign Lecturer') }}</x-danger-button>
                            </form>
                        </div>
                    </div>
                </x-modal>
            @endforeach

            @php
                $sections = $subject->sections()->orderByDesc('year')->orderByDesc('semester')->orderBy('sequence')->get();
                $now = now();
            @endphp
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">{{ __('Sections') }}</h3>
                    <a href="{{ route('lecturer.subjects.sections.create', $subject) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        {{ __('Create Section') }}
                    </a>
                </div>

                @if ($sections->isEmpty())
                    <div>
                        <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">{{ __('No sections yet') }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Create a section to open enrollment for this subject.') }}</p>
                    </div>
                @else
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($sections as $section)
                            @php
                                if ($now->lt($section->opens_at)) {
                                    $windowStatus = 'opens';
                                    $windowLabel = __('Opens :date', ['date' => $section->opens_at->format('M j, Y')]);
                                } elseif ($now->gte($section->closes_at)) {
                                    $windowStatus = 'closed';
                                    $windowLabel = __('Closed');
                                } else {
                                    $windowStatus = 'open';
                                    $windowLabel = __('Open');
                                }
                            @endphp
                            <li class="py-2 flex items-center justify-between text-sm">
                                <span class="text-gray-900 dark:text-gray-100">{{ $section->name }} ({{ $section->capacity }} {{ __('seats') }})</span>
                                <span class="flex items-center gap-3">
                                    <x-status-pill :status="$windowStatus">{{ $windowLabel }}</x-status-pill>
                                    <a href="{{ route('lecturer.subjects.sections.edit', [$subject, $section]) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-500 dark:hover:text-blue-400">{{ __('Edit') }}</a>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
