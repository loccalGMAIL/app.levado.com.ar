<x-admin-layout>
    <x-slot name="title">Logs de auditoría</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-corteza leading-tight">Logs de auditoría</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Filtros --}}
            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Comercio</label>
                    <select name="tenant_id"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todos</option>
                        @foreach($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>
                                {{ $tenant->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Actor</label>
                    <select name="actor_id"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todos</option>
                        @foreach($actors as $actor)
                            <option value="{{ $actor->id }}" @selected(request('actor_id') == $actor->id)>
                                {{ $actor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Acción</label>
                    <input type="text" name="action" value="{{ request('action') }}"
                        placeholder="ej: recipe.created"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Desde</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Hasta</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <button type="submit"
                    class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request()->hasAny(['actor_id', 'tenant_id', 'target_type', 'action', 'from', 'to']))
                    <a href="{{ route('admin.audit-logs.index') }}" class="text-sm text-masa-madre hover:underline">
                        Limpiar
                    </a>
                @endif
            </form>

            {{-- Tabla --}}
            <div class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <th class="px-4 py-3 font-medium">Fecha</th>
                            <th class="px-4 py-3 font-medium">Comercio</th>
                            <th class="px-4 py-3 font-medium">Actor</th>
                            <th class="px-4 py-3 font-medium">Acción</th>
                            <th class="px-4 py-3 font-medium">Objeto</th>
                            <th class="px-4 py-3 font-medium">IP</th>
                            <th class="px-4 py-3 font-medium">Flags</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @forelse($logs as $log)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-masa-madre whitespace-nowrap">
                                    {{ $log->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-4 py-3 text-corteza">
                                    {{ $log->tenant?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-corteza">{{ $log->actor?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs bg-miga text-corteza px-2 py-0.5 rounded">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-masa-madre text-xs">
                                    @if(!empty($log->payload['name']))
                                        <span class="text-corteza">{{ $log->payload['name'] }}</span>
                                        <span class="font-mono text-masa-madre"> ({{ $log->target_type }}#{{ $log->target_id }})</span>
                                    @elseif(!empty($log->payload['email']))
                                        <span class="text-corteza">{{ $log->payload['email'] }}</span>
                                        <span class="font-mono text-masa-madre"> ({{ $log->target_type }}#{{ $log->target_id }})</span>
                                    @else
                                        <span class="font-mono">{{ $log->target_type }}#{{ $log->target_id }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-masa-madre font-mono text-xs">
                                    {{ $log->ip_address ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($log->was_impersonating)
                                        <span class="text-xs text-horno font-medium">impersonando</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-masa-madre">
                                    No hay registros de auditoría.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if($logs->hasPages())
                    <div class="px-4 py-3 border-t border-miga">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-admin-layout>
