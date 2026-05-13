<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Te invitaron a unirte a <strong>{{ $invitation->tenant->name }}</strong>
        como <strong>{{ $invitation->role->label() }}</strong>.
        @if($userExists)
            Tu cuenta ya existe — ingresá tu contraseña para aceptar.
        @else
            Creá tu cuenta para comenzar.
        @endif
    </div>

    <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">
        @csrf

        @if(! $userExists)
            <div>
                <x-input-label for="name" value="Nombre completo" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                    :value="old('name')" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div class="mt-4">
        @else
            <div>
        @endif
                <x-input-label for="password" value="Contraseña" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

        @if(! $userExists)
            <div class="mt-4">
                <x-input-label for="password_confirmation" value="Confirmar contraseña" />
                <x-text-input id="password_confirmation" name="password_confirmation"
                    type="password" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        @endif

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Aceptar invitación
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
