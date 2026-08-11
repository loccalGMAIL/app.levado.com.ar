<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />
            {{-- El type real lo maneja Alpine; el type="password" del markup es el
                 fallback si el JS todavía no montó (o está deshabilitado). --}}
            <div class="relative mt-1" x-data="{ show: false }">
                <x-text-input id="password" class="block w-full pe-10" type="password"
                    x-bind:type="show ? 'text' : 'password'"
                    name="password" required autocomplete="current-password" />
                <button type="button" @click="show = ! show"
                    class="absolute inset-y-0 end-0 flex items-center px-3 text-masa-madre hover:text-corteza focus:outline-none focus:text-corteza"
                    aria-label="Mostrar contraseña"
                    x-bind:aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    x-bind:aria-pressed="show ? 'true' : 'false'">
                    <x-icon name="eye" class="w-5 h-5" x-show="! show" />
                    {{-- display:none inline para que no parpadee antes de que Alpine monte
                         (el proyecto no define CSS para [x-cloak]). --}}
                    <x-icon name="eye-off" class="w-5 h-5" x-show="show" style="display: none" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox"
                    class="rounded border-gray-300 text-horno shadow-sm focus:ring-horno" name="remember">
                <span class="ms-2 text-sm text-masa-madre">Recordarme</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="text-sm text-masa-madre underline hover:text-corteza rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-horno"
                    href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif

            <x-primary-button class="ms-3">
                Ingresar
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
