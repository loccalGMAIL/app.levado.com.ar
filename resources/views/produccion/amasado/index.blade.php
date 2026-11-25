<x-app-layout>
    <x-slot name="title">Amasado</x-slot>

    @php
        $dayUrl = fn (\Carbon\CarbonImmutable $day) => route('produccion.amasado.index', ['fecha' => $day->toDateString()]);
        $isToday = $date->isSameDay(today());
    @endphp

    <div class="py-8 px-4 sm:px-6 lg:px-8" x-data>
        <div class="max-w-4xl space-y-5">

            <x-list-header title="Amasado" subtitle="Qué amasar en el día, a partir de los pedidos.">
                <a href="{{ route('produccion.amasado.imprimir', ['fecha' => $date->toDateString()]) }}"
                    class="px-3 py-2 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">Imprimir</a>
            </x-list-header>

            {{-- Selector de día --}}
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <form method="GET" action="{{ route('produccion.amasado.index') }}" class="flex items-center gap-2">
                    <label for="fecha" class="sr-only">Día</label>
                    <input id="fecha" type="date" name="fecha" value="{{ $date->toDateString() }}"
                        onchange="this.form.submit()"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <span class="text-sm text-corteza font-medium capitalize">{{ $date->locale('es')->isoFormat('dddd D/M') }}</span>
                </form>
                <div class="flex items-center gap-2">
                    <a href="{{ $dayUrl($date->subDay()) }}"
                        class="px-3 py-1.5 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">← Anterior</a>
                    @unless($isToday)
                        <a href="{{ route('produccion.amasado.index') }}"
                            class="px-3 py-1.5 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">Hoy</a>
                    @endunless
                    <a href="{{ $dayUrl($date->addDay()) }}"
                        class="px-3 py-1.5 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">Siguiente →</a>
                </div>
            </div>

            @include('produccion.amasado._plan', ['plan' => $plan])

        </div>
    </div>
</x-app-layout>
