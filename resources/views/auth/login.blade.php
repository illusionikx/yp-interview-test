<x-guest-layout>
    {{-- v3.md Flowbite 4.0 login card, reproduced structurally and class-for-class (NAV-02).
         Deviations from the raw static snippet: real POST action + @csrf, name/id/value wiring,
         inline per-field errors (validation errors are NOT toasts — UX-03's toaster governs
         create/save/delete, not form validation), and real register/password.request routes. --}}
    <div class="w-full max-w-sm mx-auto bg-neutral-primary-soft p-6 border border-default rounded-base shadow-xs">
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <h5 class="text-xl font-semibold text-heading mb-6">{{ __('Sign in to our platform') }}</h5>

            <!-- Email Address -->
            <div class="mb-4">
                <label for="email" class="block mb-2.5 text-sm font-medium text-heading">{{ __('Your email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="example@company.com" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block mb-2.5 text-sm font-medium text-heading">{{ __('Your password') }}</label>
                <input type="password" id="password" name="password"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="•••••••••" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Remember Me + Lost Password -->
            <div class="flex items-start my-6">
                <div class="flex items-center">
                    <input id="checkbox-remember" type="checkbox" value="1" name="remember"
                        class="w-4 h-4 border border-default-medium rounded-xs bg-neutral-secondary-medium focus:ring-2 focus:ring-brand-soft">
                    <label for="checkbox-remember" class="ms-2 text-sm font-medium text-heading">{{ __('Remember me') }}</label>
                </div>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="ms-auto text-sm font-medium text-fg-brand hover:underline">{{ __('Lost Password?') }}</a>
                @endif
            </div>

            <button type="submit" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none w-full mb-3">{{ __('Login to your account') }}</button>

            <div class="text-sm font-medium text-body">{{ __('Not registered?') }} <a href="{{ route('register') }}" class="text-fg-brand hover:underline">{{ __('Create account') }}</a></div>
        </form>
    </div>
</x-guest-layout>
