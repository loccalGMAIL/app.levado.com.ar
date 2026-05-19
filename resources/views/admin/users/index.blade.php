<x-admin-layout>
    <x-slot name="title">Usuarios</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-corteza leading-tight">Usuarios</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Búsqueda --}}
            <form method="GET" class="flex gap-3">
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

            {{-- Tabla --}}
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nombre</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Registro</th>
                            <th class="px-4 py-3 font-medium">Tenants</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @forelse($users as $user)
                            <tr class="align-top">
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-masa-madre">
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
    </div>
</x-admin-layout>
