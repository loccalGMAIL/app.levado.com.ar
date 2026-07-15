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
    <div x-data="{ editingId: null, editingName: '' }" class="space-y-4">

        @if(session('category_error'))
            <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
                {{ session('category_error') }}
            </div>
        @endif

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
                            <span class="text-sm text-corteza">{{ $cat->name }}</span>
                            <div class="flex items-center gap-3 shrink-0">
                                <button type="button"
                                    @click="editingId = {{ $cat->id }}; editingName = '{{ addslashes($cat->name) }}'"
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
                                class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name"
                                    x-model="editingName"
                                    class="flex-1 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm py-1.5"
                                    required />
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
                class="flex items-center gap-2">
                @csrf
                <input type="text" name="name"
                    placeholder="Nombre de la categoría"
                    class="flex-1 text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm"
                    required />
                <button type="submit"
                    class="px-4 py-2 text-sm bg-corteza text-white rounded-md hover:bg-horno transition-colors whitespace-nowrap">
                    + Agregar
                </button>
            </form>
        </div>

    </div>
</x-crud-modal>
