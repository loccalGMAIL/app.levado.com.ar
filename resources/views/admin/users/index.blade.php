<x-admin-layout>
    <x-slot name="title">Usuarios</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-corteza leading-tight">Usuarios</h2>
    </x-slot>

    <div class="py-10" x-data="{
        showCreate: false,
        editUser: null,
        openEdit(user) { this.editUser = user; },
        closeEdit() { this.editUser = null; }
    }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Búsqueda y acción --}}
            <div class="flex items-center gap-3">
                <form method="GET" class="flex gap-3 flex-1">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre o email..."
                        class="flex-1 max-w-sm border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <button type="submit"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        Buscar
                    </button>
                    @if(request('search'))
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-masa-madre hover:underline self-center">
                            Limpiar
                        </a>
                    @endif
                </form>

                <button @click="showCreate = true"
                    class="px-4 py-2 bg-horno text-white text-sm rounded-md hover:bg-corteza transition-colors whitespace-nowrap">
                    + Crear usuario
                </button>
            </div>

            {{-- Tabla --}}
            <div class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="min-w-[700px] w-full text-sm text-left">
                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nombre</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Registro</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3 font-medium">Comercios</th>
                            <th class="px-4 py-3 font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @forelse($users as $user)
                            <tr class="align-top {{ $user->active ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3 text-corteza font-medium">
                                    {{ $user->name }}
                                </td>
                                <td class="px-4 py-3 text-masa-madre">
                                    {{ $user->email }}
                                </td>
                                <td class="px-4 py-3 text-masa-madre text-xs whitespace-nowrap">
                                    {{ $user->created_at->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($user->active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700">activo</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($user->tenantUsers->isEmpty())
                                        <span class="text-xs text-masa-madre">—</span>
                                    @else
                                        <div class="space-y-1">
                                            @foreach($user->tenantUsers as $tu)
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('admin.tenants.show', $tu->tenant_id) }}"
                                                        class="text-xs text-corteza hover:text-horno hover:underline">
                                                        {{ $tu->tenant->name }}
                                                    </a>
                                                    <span class="text-xs text-masa-madre">· {{ $tu->role->label() }}</span>
                                                    @if($tu->active)
                                                        <span class="text-[11px] text-green-600 font-medium">activo</span>
                                                    @else
                                                        <span class="text-[11px] text-gray-400">inactivo</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        {{-- Editar --}}
                                        <button
                                            @click="openEdit({ id: {{ $user->id }}, name: '{{ addslashes($user->name) }}', email: '{{ addslashes($user->email) }}' })"
                                            title="Editar usuario"
                                            class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        {{-- Enviar recuperación --}}
                                        <form method="POST"
                                            action="{{ route('admin.users.send-password-reset', $user) }}"
                                            onsubmit="return confirm('¿Enviar correo de recuperación a {{ addslashes($user->email) }}?')">
                                            @csrf
                                            <button type="submit" title="Enviar recuperación de contraseña"
                                                class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                            </button>
                                        </form>

                                        {{-- Activar / Desactivar --}}
                                        <form method="POST"
                                            action="{{ route('admin.users.toggle-active', $user) }}"
                                            onsubmit="return confirm('{{ $user->active ? '¿Desactivar a ' . addslashes($user->name) . '? No podrá ingresar a la app.' : '¿Activar a ' . addslashes($user->name) . '?' }}')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                title="{{ $user->active ? 'Desactivar usuario' : 'Activar usuario' }}"
                                                class="p-1.5 rounded transition-colors {{ $user->active ? 'text-masa-madre hover:text-red-600 hover:bg-red-50' : 'text-masa-madre hover:text-green-600 hover:bg-green-50' }}">
                                                @if($user->active)
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                    </svg>
                                                @else
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @endif
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-masa-madre">
                                    No se encontraron usuarios.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if($users->hasPages())
                    <div class="px-4 py-3 border-t border-miga">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>

            <p class="text-xs text-masa-madre">{{ $users->total() }} usuario(s) en total.</p>

        </div>

        {{-- Modal: Crear usuario --}}
        <div x-show="showCreate" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @keydown.escape.window="showCreate = false">

            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6"
                @click.outside="showCreate = false">

                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-corteza">Crear usuario</h3>
                    <button @click="showCreate = false" class="text-masa-madre hover:text-corteza text-xl leading-none">&times;</button>
                </div>

                <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-medium text-corteza mb-1">Nombre</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-corteza mb-1">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        @error('email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="tenant_id" class="block text-sm font-medium text-corteza mb-1">Comercio</label>
                        <select id="tenant_id" name="tenant_id" required
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                            <option value="">— Seleccionar comercio —</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}" {{ old('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                    {{ $tenant->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('tenant_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-corteza mb-1">Rol</label>
                        <select id="role" name="role" required
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                            <option value="">— Seleccionar rol —</option>
                            @foreach(\App\Enums\TenantUserRole::cases() as $role)
                                <option value="{{ $role->value }}" {{ old('role') === $role->value ? 'selected' : '' }}>
                                    {{ $role->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-xs text-masa-madre">
                        Si el usuario ya existe, solo se lo asociará al comercio seleccionado.
                        En ambos casos se enviará un correo para establecer la contraseña.
                    </p>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showCreate = false"
                            class="px-4 py-2 text-sm border border-miga rounded-md hover:bg-miga transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm bg-horno text-white rounded-md hover:bg-corteza transition-colors">
                            Crear y enviar correo
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Editar usuario --}}
        <div x-show="editUser !== null" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @keydown.escape.window="closeEdit()">

            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6"
                @click.outside="closeEdit()">

                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-corteza">Editar usuario</h3>
                    <button @click="closeEdit()" class="text-masa-madre hover:text-corteza text-xl leading-none">&times;</button>
                </div>

                <template x-if="editUser">
                    <form method="POST" :action="`/admin/users/${editUser.id}`" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label class="block text-sm font-medium text-corteza mb-1">Nombre</label>
                            <input type="text" name="name" :value="editUser.name" required
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-corteza mb-1">Email</label>
                            <input type="email" name="email" :value="editUser.email" required
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="closeEdit()"
                                class="px-4 py-2 text-sm border border-miga rounded-md hover:bg-miga transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="px-4 py-2 text-sm bg-horno text-white rounded-md hover:bg-corteza transition-colors">
                                Guardar cambios
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
</x-admin-layout>
