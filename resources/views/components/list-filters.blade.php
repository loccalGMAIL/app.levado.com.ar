{{--
    Formulario GET de búsqueda + estado de los listados. Los hidden de sort/dir
    preservan el orden vigente al filtrar.
--}}
@props([
    'resetRoute',
    'placeholder' => 'Buscar por nombre...',
    'status' => true,
])

<form method="GET" class="flex gap-3 items-end flex-wrap">
    <input type="hidden" name="sort" value="{{ request('sort') }}">
    <input type="hidden" name="dir" value="{{ request('dir') }}">

    <div class="flex-1 min-w-48">
        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="{{ $placeholder }}"
            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
    </div>

    @if($status)
        <select name="status"
            class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
            <option value="">Todos</option>
            <option value="active"   @selected(request('status') === 'active')>Activos</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
        </select>
    @endif

    {{ $slot }}

    <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
        Filtrar
    </button>

    @if(request('search') || request('status'))
        <a href="{{ $resetRoute }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
    @endif
</form>
