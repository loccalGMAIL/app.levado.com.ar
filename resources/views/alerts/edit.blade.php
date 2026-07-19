<x-app-layout>
    <x-slot name="title">Alertas</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6 max-w-3xl">

            @if(session('status') === 'alerts-updated')
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    Cambios guardados.
                </div>
            @endif

            <div>
                <h1 class="text-xl font-semibold text-corteza">Alertas</h1>
                <p class="text-sm text-masa-madre mt-1">
                    Elegí qué avisos querés recibir en el panel de inicio y en el centro de alertas.
                    <a href="{{ route('notifications.index') }}" class="text-horno hover:underline">Ver alertas activas →</a>
                </p>
            </div>

            <form method="POST" action="{{ route('alerts.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                {{-- Stock bajo --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="low_stock_enabled" value="1"
                               class="mt-0.5 rounded text-horno border-gray-300 focus:ring-horno"
                               @checked($settings['low_stock_enabled'])>
                        <span class="text-sm">
                            <span class="font-semibold text-corteza">📦 Stock bajo o negativo</span>
                            <span class="block text-masa-madre">Avisa cuando un insumo o descartable queda en o por debajo de su mínimo.</span>
                        </span>
                    </label>
                </div>

                {{-- Salto de costo --}}
                <div class="bg-white rounded-lg shadow p-6 space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="cost_spike_enabled" value="1"
                               class="mt-0.5 rounded text-horno border-gray-300 focus:ring-horno"
                               @checked($settings['cost_spike_enabled'])>
                        <span class="text-sm">
                            <span class="font-semibold text-corteza">📈 Salto de costo en compra</span>
                            <span class="block text-masa-madre">Avisa cuando una compra sube el costo de un ítem por encima del umbral.</span>
                        </span>
                    </label>
                    <div class="pl-8">
                        <x-input-label for="cost_spike_threshold_pct" value="Umbral de aumento (%)" />
                        <x-text-input id="cost_spike_threshold_pct" name="cost_spike_threshold_pct"
                            type="number" min="1" max="1000" step="1"
                            class="mt-1 block w-32"
                            :value="old('cost_spike_threshold_pct', $settings['cost_spike_threshold_pct'])" />
                        <x-input-error :messages="$errors->get('cost_spike_threshold_pct')" class="mt-2" />
                    </div>
                </div>

                {{-- Costo desactualizado --}}
                <div class="bg-white rounded-lg shadow p-6 space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="stale_cost_enabled" value="1"
                               class="mt-0.5 rounded text-horno border-gray-300 focus:ring-horno"
                               @checked($settings['stale_cost_enabled'])>
                        <span class="text-sm">
                            <span class="font-semibold text-corteza">🕒 Costo desactualizado</span>
                            <span class="block text-masa-madre">Avisa cuando un insumo activo no actualiza su costo hace demasiado tiempo.</span>
                        </span>
                    </label>
                    <div class="pl-8">
                        <x-input-label for="stale_cost_days" value="Días sin actualizar" />
                        <x-text-input id="stale_cost_days" name="stale_cost_days"
                            type="number" min="1" max="3650" step="1"
                            class="mt-1 block w-32"
                            :value="old('stale_cost_days', $settings['stale_cost_days'])" />
                        <x-input-error :messages="$errors->get('stale_cost_days')" class="mt-2" />
                    </div>
                </div>

                {{-- Compras sin imputar --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="unapplied_purchase_enabled" value="1"
                               class="mt-0.5 rounded text-horno border-gray-300 focus:ring-horno"
                               @checked($settings['unapplied_purchase_enabled'])>
                        <span class="text-sm">
                            <span class="font-semibold text-corteza">🧾 Compras sin imputar</span>
                            <span class="block text-masa-madre">Avisa cuando una compra tiene renglones pendientes de asociar o imputar.</span>
                        </span>
                    </label>
                </div>

                <div class="pb-2">
                    <x-primary-button>Guardar cambios</x-primary-button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
