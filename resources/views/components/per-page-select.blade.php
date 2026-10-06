{{--
    Selector de filas por página. Cambia ?per_page= conservando filtros y orden
    de la URL actual y vuelve a la primera página. Va fuera del
    `@if($paginator->hasPages())`: con 200 por página puede entrar todo en una
    sola y el select tiene que seguir ahí para volver a una cantidad menor.
    Opciones y persistencia: App\Http\Controllers\Concerns\ResolvesPerPage.

    Uso: <x-per-page-select :paginator="$recipes" />
         <x-per-page-select :paginator="$recipeRows" fragment="tabla-recetas" />
--}}
@props(['paginator', 'fragment' => null])

@if($paginator->total() > min(\App\Http\Controllers\Controller::PER_PAGE_OPTIONS))
    <label class="inline-flex items-center gap-2 text-xs text-masa-madre print:hidden">
        Mostrar
        <select aria-label="Filas por página"
            class="rounded-md border-miga bg-white py-1 pl-2 pr-7 text-xs text-corteza focus:border-masa-madre focus:ring-masa-madre"
            onchange="const url = new window.URL(window.location.href); url.searchParams.set('per_page', this.value); url.searchParams.delete(@js($paginator->getPageName())); url.hash = @js($fragment ?? ''); window.location.href = url.toString();">
            @foreach(\App\Http\Controllers\Controller::PER_PAGE_OPTIONS as $option)
                <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
@endif
