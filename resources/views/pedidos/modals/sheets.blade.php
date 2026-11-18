{{-- Configurar planillas: alta, renombrado, pausa y borrado. Cada fila
     tiene sus propios formularios hermanos (no se anidan). --}}
<x-crud-modal name="order-sheets-manage" title="Planillas de pedidos" max-width="2xl" :show="$errors->has('name')">
    <div class="space-y-5">
        <p class="text-sm text-masa-madre">
            Armá una planilla por cada tipo de pedido que manejás (por ejemplo: Secos, Diarios, Tortas, Reparto).
            Cada una es una grilla de lunes a domingo con sus propios artículos.
        </p>

        @if($allSheets->isNotEmpty())
            <div class="divide-y divide-miga border border-miga rounded-md">
                @foreach($allSheets as $row)
                    <div class="p-3 flex items-center gap-2 flex-wrap">
                        <form method="POST" action="{{ route('pedidos.planillas.update', $row) }}" class="flex items-center gap-2 flex-1 min-w-64">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ old('name', $row->name) }}" required maxlength="100" aria-label="Nombre"
                                class="flex-1 min-w-0 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno {{ $row->active ? '' : 'opacity-60' }}">
                            <button type="submit" class="px-3 py-1.5 bg-tinta text-white text-xs rounded-md hover:bg-horno transition-colors">Guardar</button>
                        </form>

                        <form method="POST" action="{{ route('pedidos.planillas.toggle-active', $row) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="px-2 py-1.5 text-xs rounded-md border border-miga hover:bg-miga transition-colors {{ $row->active ? 'text-masa-madre' : 'text-green-700' }}">
                                {{ $row->active ? 'Pausar' : 'Activar' }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('pedidos.planillas.destroy', $row) }}"
                            onsubmit="return confirm('¿Borrar la planilla {{ addslashes($row->name) }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-2 py-1.5 text-xs rounded-md text-red-600 hover:bg-red-50 transition-colors">Borrar</button>
                        </form>
                    </div>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('name')" />
        @endif

        <form method="POST" action="{{ route('pedidos.planillas.store') }}" class="flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <x-input-label for="new_sheet_name" value="Nueva planilla" />
                <x-text-input id="new_sheet_name" name="name" type="text" class="mt-1 block w-full" placeholder="Ej: Tortas" required maxlength="100" />
            </div>
            <x-primary-button data-loading="Creando…">Crear</x-primary-button>
        </form>
    </div>
</x-crud-modal>
