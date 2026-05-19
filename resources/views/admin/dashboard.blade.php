<x-admin-layout>
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-corteza leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- Widgets --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="bg-white rounded-lg shadow p-5 text-center">
                    <div class="text-3xl font-bold text-corteza">{{ $totalTenants }}</div>
                    <div class="text-sm text-masa-madre mt-1">Tenants totales</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 text-center">
                    <div class="text-3xl font-bold text-green-600">{{ $activeTenants }}</div>
                    <div class="text-sm text-masa-madre mt-1">Activos</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 text-center">
                    <div class="text-3xl font-bold text-gray-400">{{ $inactiveTenants }}</div>
                    <div class="text-sm text-masa-madre mt-1">Inactivos</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 text-center">
                    <div class="text-3xl font-bold text-corteza">{{ $totalUsers }}</div>
                    <div class="text-sm text-masa-madre mt-1">Usuarios totales</div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 text-center">
                    <div class="text-3xl font-bold text-membrillo">{{ $recentlyActiveTenants }}</div>
                    <div class="text-sm text-masa-madre mt-1">Activos últimos 7 días</div>
                </div>
            </div>

            @if($dormantTenants > 0)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
                    <strong>{{ $dormantTenants }}</strong> tenant(s) activo(s) sin actividad en los últimos 30 días — posible churn.
                </div>
            @endif

            {{-- Feed de auditoría --}}
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-corteza">Últimas acciones del backoffice</h3>
                    <a href="{{ route('admin.audit-logs.index') }}" class="text-sm text-horno hover:underline">Ver todo →</a>
                </div>

                @if($recentLogs->isEmpty())
                    <p class="text-sm text-masa-madre">Sin registros aún.</p>
                @else
                    <table class="w-full text-sm text-left">
                        <thead class="text-masa-madre border-b border-miga">
                            <tr>
                                <th class="pb-2 font-medium">Fecha</th>
                                <th class="pb-2 font-medium">Actor</th>
                                <th class="pb-2 font-medium">Acción</th>
                                <th class="pb-2 font-medium">Target</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($recentLogs as $log)
                                <tr>
                                    <td class="py-2 text-masa-madre font-mono text-xs">
                                        {{ $log->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-2">{{ $log->actor?->name ?? '—' }}</td>
                                    <td class="py-2">
                                        <span class="font-mono text-xs bg-miga text-corteza px-2 py-0.5 rounded">
                                            {{ $log->action }}
                                        </span>
                                        @if($log->was_impersonating)
                                            <span class="ml-1 text-xs text-horno">(impersonando)</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-masa-madre">{{ $log->target_type }}#{{ $log->target_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>
</x-admin-layout>
