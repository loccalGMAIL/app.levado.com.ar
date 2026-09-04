<x-app-layout>
    <x-slot name="title">Plantillas de producción</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            <div>
                <a href="{{ route('production-orders.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">← Órdenes de producción</a>
                <h2 class="text-base font-semibold text-corteza mt-2">Plantillas</h2>
                <p class="text-sm text-masa-madre mt-0.5">Órdenes preestablecidas: usalas para armar la orden del día sin cargar todo de nuevo.</p>
            </div>

            @if($templates->isEmpty())
                <x-empty-state>
                    Todavía no hay plantillas. Desde el detalle de una orden, "Guardar como plantilla".
                </x-empty-state>
            @else
                <div class="space-y-3">
                    @foreach($templates as $template)
                        <div class="bg-white border border-miga rounded-lg shadow-sm px-5 py-4 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-medium text-corteza text-sm">{{ $template->name }}</div>
                                <x-production-order-type-badge :type="$template->type" class="mt-1" />
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <form method="POST" action="{{ route('production-order-templates.use', $template) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="date" name="scheduled_for" value="{{ now()->toDateString() }}"
                                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                    <button type="submit" class="px-3 py-1.5 bg-corteza text-white text-xs rounded-md hover:bg-horno transition-colors">Usar</button>
                                </form>
                                <form method="POST" action="{{ route('production-order-templates.destroy', $template) }}"
                                    onsubmit="return confirm('¿Eliminar esta plantilla?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded text-masa-madre hover:text-red-500 hover:bg-red-50 transition-colors" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
