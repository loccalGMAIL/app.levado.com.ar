<x-admin-layout>
    <x-slot name="title">Tenants</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-corteza leading-tight">Tenants</h2>
            <a href="{{ route('admin.tenants.create') }}"
                class="px-4 py-2 bg-horno text-white text-sm font-medium rounded-md hover:bg-corteza transition-colors">
                + Nuevo tenant
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Filtros --}}
            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre o país..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <div>
                    <select name="status"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todos</option>
                        <option value="active" @selected(request('status') === 'active')>Activos</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                    </select>
                </div>
                <button type="submit"
                    class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('admin.tenants.index') }}" class="text-sm text-masa-madre hover:underline">
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
                            <th class="px-4 py-3 font-medium">País</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3 font-medium">Usuarios activos</th>
                            <th class="px-4 py-3 font-medium">Creado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @forelse($tenants as $tenant)
                            <tr class="{{ $tenant->active ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3 font-medium text-corteza">
                                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="hover:text-horno">
                                        {{ $tenant->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-masa-madre uppercase font-mono text-xs">
                                    {{ $tenant->country }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($tenant->active)
                                        <span class="text-green-600 text-xs font-medium">Activo</span>
                                    @else
                                        <span class="text-gray-400 text-xs font-medium">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-masa-madre">
                                    {{ $tenant->active_users_count }}
                                </td>
                                <td class="px-4 py-3 text-masa-madre text-xs">
                                    {{ $tenant->created_at->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.tenants.show', $tenant) }}"
                                        class="text-horno hover:underline text-xs">Ver →</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-masa-madre">
                                    No se encontraron tenants.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if($tenants->hasPages())
                    <div class="px-4 py-3 border-t border-miga">
                        {{ $tenants->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-admin-layout>
