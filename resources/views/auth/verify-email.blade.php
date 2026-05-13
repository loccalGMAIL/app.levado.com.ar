<x-guest-layout>
    <div class="mb-4 text-sm text-masa-madre">
        Te enviamos un link de verificación a tu correo. Hacé clic en el link para activar tu cuenta.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            Te enviamos un nuevo link de verificación.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                Reenviar correo de verificación
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                class="text-sm text-masa-madre underline hover:text-corteza rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-horno">
                Cerrar sesión
            </button>
        </form>
    </div>
</x-guest-layout>
