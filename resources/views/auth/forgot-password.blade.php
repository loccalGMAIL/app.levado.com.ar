<x-guest-layout>
    <div class="mb-4 text-sm text-masa-madre">
        Ingresá tu correo y te enviaremos un link para restablecer tu contraseña.
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email"
                name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Enviar link de recuperación
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
