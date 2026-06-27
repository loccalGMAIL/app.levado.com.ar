<x-app-layout>
    <x-slot name="title">Personal</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            {{-- Invitar miembro --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-base font-semibold text-corteza mb-4">Invitar persona</h2>

                <form method="POST" action="{{ route('team.invitations.store') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
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
                            class="mt-1 border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
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
                <div class="bg-white shadow rounded-lg p-6" x-data="{ mobileExpanded: false }">
                    <h2 class="text-base font-semibold text-corteza mb-4">Invitaciones pendientes</h2>

                    {{-- Cards (mobile) --}}
                    <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="space-y-3">
                        @foreach($pendingInvitations as $invitation)
                            <div class="border border-miga rounded-lg p-4">
                                <div class="font-medium text-corteza text-sm">{{ $invitation->email }}</div>
                                <div class="text-xs text-masa-madre mt-0.5">{{ $invitation->role->label() }} · Expira {{ $invitation->expires_at->format('d/m/Y') }}</div>
                                <div class="mt-3 pt-3 border-t border-miga">
                                    <form method="POST" action="{{ route('team.invitations.destroy', $invitation) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="w-full py-1.5 px-3 text-sm border border-red-300 text-red-600 hover:bg-red-50 rounded transition-colors">
                                            Cancelar invitación
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" @click="mobileExpanded = true"
                            class="w-full py-2 text-sm text-masa-madre hover:text-corteza text-center">
                            Ver tabla completa ↓
                        </button>
                    </div>

                    {{-- Tabla (desktop siempre, mobile si está expandida) --}}
                    <div :class="mobileExpanded ? '' : 'hidden md:block'">
                        <div class="md:hidden mb-3">
                            <button type="button" @click="mobileExpanded = false"
                                class="text-sm text-masa-madre hover:text-corteza">
                                ← Volver a cards
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-masa-madre border-b border-miga">
                                    <tr>
                                        <th class="pb-2">Email</th>
                                        <th class="pb-2">Rol</th>
                                        <th class="pb-2">Expira</th>
                                        <th class="pb-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-miga">
                                    @foreach($pendingInvitations as $invitation)
                                        <tr>
                                            <td class="py-2 text-corteza">{{ $invitation->email }}</td>
                                            <td class="py-2 text-masa-madre">{{ $invitation->role->label() }}</td>
                                            <td class="py-2 text-masa-madre">{{ $invitation->expires_at->format('d/m/Y') }}</td>
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
                    </div>
                </div>
            @endif

            {{-- Miembros del equipo --}}
            <div class="bg-white shadow rounded-lg p-6" x-data="{ mobileExpanded: false }">
                <h2 class="text-base font-semibold text-corteza mb-4">Miembros</h2>

                {{-- Cards (mobile) --}}
                <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="space-y-3">
                    @foreach($members as $member)
                        <div class="border border-miga rounded-lg p-4 {{ $member->active ? '' : 'opacity-50' }}">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium text-corteza">{{ $member->user->name }}</div>
                                    <div class="text-xs text-masa-madre mt-0.5">{{ $member->user->email }}</div>
                                </div>
                                @if($member->active)
                                    <span class="text-green-600 text-xs font-medium">Activo</span>
                                @else
                                    <span class="text-gray-400 text-xs font-medium">Inactivo</span>
                                @endif
                            </div>
                            <div class="mt-2">
                                @if($member->user->id !== $currentUser->id)
                                    <form method="POST" action="{{ route('team.members.role', $member) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" onchange="this.form.submit()"
                                            class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-horno">
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
                                    <span class="text-sm text-masa-madre">{{ $member->role->label() }}</span>
                                @endif
                            </div>
                            @if($member->user->id !== $currentUser->id)
                                <div class="mt-3 pt-3 border-t border-miga">
                                    @if($member->active)
                                        <form method="POST" action="{{ route('team.members.deactivate', $member) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                class="w-full py-1.5 px-3 text-sm border border-red-300 text-red-600 hover:bg-red-50 rounded transition-colors">
                                                Desactivar
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('team.members.activate', $member) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                class="w-full py-1.5 px-3 text-sm border border-green-300 text-horno hover:bg-green-50 rounded transition-colors">
                                                Reactivar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <button type="button" @click="mobileExpanded = true"
                        class="w-full py-2 text-sm text-masa-madre hover:text-corteza text-center">
                        Ver tabla completa ↓
                    </button>
                </div>

                {{-- Tabla (desktop siempre, mobile si está expandida) --}}
                <div :class="mobileExpanded ? '' : 'hidden md:block'">
                    <div class="md:hidden mb-3">
                        <button type="button" @click="mobileExpanded = false"
                            class="text-sm text-masa-madre hover:text-corteza">
                            ← Volver a cards
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-masa-madre border-b border-miga">
                                <tr>
                                    <th class="pb-2">Nombre</th>
                                    <th class="pb-2">Email</th>
                                    <th class="pb-2">Rol</th>
                                    <th class="pb-2">Estado</th>
                                    <th class="pb-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                @foreach($members as $member)
                                    <tr class="{{ $member->active ? '' : 'opacity-50' }}">
                                        <td class="py-2 text-corteza">{{ $member->user->name }}</td>
                                        <td class="py-2 text-masa-madre">{{ $member->user->email }}</td>
                                        <td class="py-2">
                                            @if($member->user->id !== $currentUser->id)
                                                <form method="POST"
                                                    action="{{ route('team.members.role', $member) }}"
                                                    class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="role" onchange="this.form.submit()"
                                                        class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-horno">
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
                                                <span class="text-masa-madre">{{ $member->role->label() }}</span>
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
                                                        <button type="submit" class="text-horno hover:underline text-xs">
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

        </div>
    </div>
</x-app-layout>
