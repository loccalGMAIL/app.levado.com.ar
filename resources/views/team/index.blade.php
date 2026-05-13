<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi equipo
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Mensajes de estado --}}
            @if (session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Invitar miembro --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Invitar persona</h3>

                <form method="POST" action="{{ route('team.invitations.store') }}" class="flex gap-3 items-end flex-wrap">
                    @csrf
                    <div class="flex-1 min-w-48">
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email"
                            class="mt-1 block w-full" :value="old('email')" placeholder="nombre@ejemplo.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="role" value="Rol" />
                        <select id="role" name="role"
                            class="mt-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach($roles as $role)
                                @if($role->value !== 'super_admin')
                                    <option value="{{ $role->value }}" @selected(old('role') === $role->value)>
                                        {{ $role->label() }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-1" />
                    </div>
                    <x-primary-button type="submit">Enviar invitación</x-primary-button>
                </form>
            </div>

            {{-- Invitaciones pendientes --}}
            @if($pendingInvitations->isNotEmpty())
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Invitaciones pendientes</h3>
                    <table class="w-full text-sm text-left">
                        <thead class="text-gray-500 border-b">
                            <tr>
                                <th class="pb-2">Email</th>
                                <th class="pb-2">Rol</th>
                                <th class="pb-2">Expira</th>
                                <th class="pb-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($pendingInvitations as $invitation)
                                <tr>
                                    <td class="py-2 text-gray-700">{{ $invitation->email }}</td>
                                    <td class="py-2 text-gray-700">{{ $invitation->role->label() }}</td>
                                    <td class="py-2 text-gray-500">{{ $invitation->expires_at->format('d/m/Y') }}</td>
                                    <td class="py-2 text-right">
                                        <form method="POST"
                                            action="{{ route('team.invitations.destroy', $invitation) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="text-red-600 hover:underline text-xs">
                                                Cancelar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Miembros del equipo --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Miembros</h3>
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-500 border-b">
                        <tr>
                            <th class="pb-2">Nombre</th>
                            <th class="pb-2">Email</th>
                            <th class="pb-2">Rol</th>
                            <th class="pb-2">Estado</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($members as $member)
                            <tr class="{{ $member->active ? '' : 'opacity-50' }}">
                                <td class="py-2 text-gray-800">{{ $member->user->name }}</td>
                                <td class="py-2 text-gray-600">{{ $member->user->email }}</td>
                                <td class="py-2">
                                    @if($member->user->id !== $currentUser->id)
                                        <form method="POST"
                                            action="{{ route('team.members.role', $member) }}"
                                            class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" onchange="this.form.submit()"
                                                class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500">
                                                @foreach($roles as $role)
                                                    @if($role->value !== 'super_admin')
                                                        <option value="{{ $role->value }}"
                                                            @selected($member->role === $role)>
                                                            {{ $role->label() }}
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="text-gray-600">{{ $member->role->label() }}</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @if($member->active)
                                        <span class="text-green-600 text-xs font-medium">Activo</span>
                                    @else
                                        <span class="text-gray-400 text-xs font-medium">Inactivo</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    @if($member->user->id !== $currentUser->id)
                                        @if($member->active)
                                            <form method="POST"
                                                action="{{ route('team.members.deactivate', $member) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-red-600 hover:underline text-xs">
                                                    Desactivar
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST"
                                                action="{{ route('team.members.activate', $member) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-indigo-600 hover:underline text-xs">
                                                    Reactivar
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
