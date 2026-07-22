@props([
    'name',
    'title',
    'categories',
    'show' => false,
    'storeRoute',
    'updateRoute',
    'destroyRoute',
])

<x-crud-modal :name="$name" :title="$title" :show="$show">
    <div x-data="{ editingId: null, editingName: '', editingProducible: true }" class="space-y-4">

        @if(session('category_error'))
            <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
                {{ session('category_error') }}
            </div>
        @endif

        <p class="text-xs text-masa-madre">Marcá «Se produce» para que los elaborados de la categoría aparezcan en Producción.</p>

        {{-- Lista de categorías existentes --}}
        @if($categories->isEmpty())
            <p class="text-sm text-masa-madre text-center py-2">No hay categorías todavía.</p>
        @else
            <ul class="divide-y divide-miga">
                @foreach($categories as $cat)
                    <li class="py-2">
                        {{-- Modo visualización --}}
                        <div x-show="editingId !== {{ $cat->id }}"
                            class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-sm text-corteza">
                                {{ $cat->name }}
                                @if($cat->producible)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-green-100 text-green-700">se produce</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-gray-100 text-gray-500">no se produce</span>
                                @endif
                            </span>
                            <div class="flex items-center gap-3 shrink-0">
                                <button type="button"
                                    @click="editingId = {{ $cat->id }}; editingName = '{{ addslashes($cat->name) }}'; editingProducible = {{ $cat->producible ? 'true' : 'false' }}"
                                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                    Editar
                                </button>
                                <form method="POST" action="{{ route($destroyRoute, $cat) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-xs text-red-400 hover:text-red-600 hover:underline"
                                        onclick="return confirm('¿Eliminar «{{ addslashes($cat->name) }}»?')">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Modo edición inline --}}
                        <div x-show="editingId === {{ $cat->id }}" x-cloak>
                            <form method="POST" action="{{ route($updateRoute, $cat) }}"
                                class="flex items-center gap-2 flex-wrap">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name"
                                    x-model="editingName"
                                    class="flex-1 min-w-40 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm py-1.5"
                                    required />
                                <label class="flex items-center gap-1.5 text-xs text-masa-madre">
                                    <input type="hidden" name="producible" value="0">
                                    <input type="checkbox" name="producible" value="1" x-model="editingProducible"
                                        class="rounded text-corteza focus:ring-horno">
                                    Se produce
                                </label>
                                <button type="submit"
                                    class="px-3 py-1.5 text-xs bg-corteza text-white rounded-md hover:bg-horno transition-colors">
                                    Guardar
                                </button>
                                <button type="button"
                                    @click="editingId = null"
                                    class="px-3 py-1.5 text-xs text-masa-madre hover:text-corteza">
                                    Cancelar
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Agregar nueva categoría --}}
        <div class="border-t border-miga pt-4">
            <p class="text-xs font-medium text-masa-madre mb-2">Nueva categoría</p>
            <form method="POST" action="{{ route($storeRoute) }}"
                class="flex items-center gap-2 flex-wrap">
                @csrf
                <input type="text" name="name"
                    placeholder="Nombre de la categoría"
                    class="flex-1 min-w-40 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm"
                    required />
                <label class="flex items-center gap-1.5 text-xs text-masa-madre">
                    <input type="hidden" name="producible" value="0">
                    <input type="checkbox" name="producible" value="1" checked
                        class="rounded text-corteza focus:ring-horno">
                    Se produce
                </label>
                <button type="submit"
                    class="px-4 py-2 text-sm bg-corteza text-white rounded-md hover:bg-horno transition-colors whitespace-nowrap">
                    + Agregar
                </button>
            </form>
        </div>

    </div>
</x-crud-modal>
