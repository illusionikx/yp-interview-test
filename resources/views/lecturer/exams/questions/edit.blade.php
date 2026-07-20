<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit question') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @include('lecturer.exams.questions._form', ['exam' => $exam, 'question' => $question, 'attemptCounts' => $attemptCounts])
            </div>
        </div>
    </div>
</x-app-layout>
