<x-guest-layout>
    {{-- Card treatment moved here from layouts/guest.blade.php's now-neutral slot wrapper (see
         that file's comment) — tokenized so this page dark-flips correctly instead of silently
         staying light. --}}
    <div class="px-6 py-4 bg-neutral-primary-soft shadow-md overflow-hidden sm:rounded-lg">
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
    </div>
</x-guest-layout>
