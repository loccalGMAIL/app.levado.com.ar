<x-admin-layout>
    <x-slot name="title">{{ $tenant->name }}</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.tenants.index') }}" class="text-masa-madre hover:text-corteza text-sm">← Tenants</a>
                <span class="text-miga">/</span>
                <h2 class="font-semibold text-xl text-corteza leading-tight">{{ $tenant->name }}</h2>
                @if($tenant->active)
                    <span class="text-green-600 text-xs font-medium bg-green-50 px-2 py-0.5 rounded-full">Activo</span>
                @else
                    <span class="text-gray-400 text-xs font-medium bg-gray-100 px-2 py-0.5 rounded-full">Inactivo</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.tenants.edit', $tenant) }}"
                    class="px-3 py-1.5 text-sm border border-miga rounded-md hover:bg-miga transition-colors">
                    Editar
                </a>
                <form method="POST" action="{{ route('admin.tenants.toggle-active', $tenant) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                        class="px-3 py-1.5 text-sm border rounded-md transition-colors
                            {{ $tenant->active
                                ? 'border-red-200 text-red-600 hover:bg-red-50'
                                : 'border-green-200 text-green-600 hover:bg-green-50' }}">
                        {{ $tenant->active ? 'Desactivar' : 'Activar' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.impersonate.start', $tenant) }}">
                    @csrf
                    <button type="submit"
                        class="px-3 py-1.5 text-sm bg-horno text-white rounded-md hover:bg-corteza transition-colors">
                        Operar como este tenant
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-10" x-data="{ tab: 'config' }">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Tabs --}}
            <div class="border-b border-miga flex gap-6">
                <button @click="tab = 'config'"
                    :class="tab === 'config' ? 'border-b-2 border-horno text-horno' : 'text-masa-madre hover:text-corteza'"
                    class="pb-3 text-sm font-medium transition-colors">
                    Configuración
                </button>
                <button @click="tab = 'users'"
                    :class="tab === 'users' ? 'border-b-2 border-horno text-horno' : 'text-masa-madre hover:text-corteza'"
                    class="pb-3 text-sm font-medium transition-colors">
                    Usuarios ({{ $tenant->total_users }})
                </button>
                <button @click="tab = 'metrics'"
                    :class="tab === 'metrics' ? 'border-b-2 border-horno text-horno' : 'text-masa-madre hover:text-corteza'"
                    class="pb-3 text-sm font-medium transition-colors">
                    Métricas
                </button>
            </div>

            {{-- Tab: Configuración --}}
            <div x-show="tab === 'config'" class="bg-white shadow rounded-lg p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                    <div>
                        <dt class="text-masa-madre font-medium">Nombre</dt>
                        <dd class="mt-1 text-corteza">{{ $tenant->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-masa-madre font-medium">País</dt>
                        <dd class="mt-1 text-corteza uppercase font-mono">{{ $tenant->country }}</dd>
                    </div>
                    <div>
                        <dt class="text-masa-madre font-medium">Moneda</dt>
                        <dd class="mt-1 text-corteza uppercase font-mono">{{ $tenant->currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-masa-madre font-medium">Horas productivas/mes</dt>
                        <dd class="mt-1 text-corteza">{{ $tenant->productive_hours_month ? $tenant->productive_hours_month . 'h' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-masa-madre font-medium">Creado</dt>
                        <dd class="mt-1 text-corteza">{{ $tenant->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-masa-madre font-medium">Última modificación</dt>
                        <dd class="mt-1 text-corteza">{{ $tenant->updated_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Tab: Usuarios --}}
            <div x-show="tab === 'users'" class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nombre</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Rol</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($tenant->tenantUsers as $tenantUser)
                            <tr class="{{ $tenantUser->active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-3 text-corteza">{{ $tenantUser->user->name }}</td>
                                <td class="px-4 py-3 text-masa-madre">{{ $tenantUser->user->email }}</td>
                                <td class="px-4 py-3 text-masa-madre">{{ $tenantUser->role->label() }}</td>
                                <td class="px-4 py-3">
                                    @if($tenantUser->active)
                                        <span class="text-green-600 text-xs font-medium">Activo</span>
                                    @else
                                        <span class="text-gray-400 text-xs font-medium">Inactivo</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tab: Métricas --}}
            <div x-show="tab === 'metrics'" class="bg-white shadow rounded-lg p-6">
                <div class="grid grid-cols-3 gap-6 text-sm">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-corteza">{{ $metrics['active_users'] }}</div>
                        <div class="text-masa-madre mt-1">Usuarios activos</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-corteza">{{ $metrics['total_users'] }}</div>
                        <div class="text-masa-madre mt-1">Usuarios totales</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-membrillo">{{ $metrics['pending_invitations'] }}</div>
                        <div class="text-masa-madre mt-1">Invitaciones pendientes</div>
                    </div>
                </div>
                <p class="text-xs text-masa-madre text-center mt-6">
                    Métricas de módulos de costos disponibles cuando se complete la Etapa 2.
                </p>
            </div>

        </div>
    </div>
</x-admin-layout>
