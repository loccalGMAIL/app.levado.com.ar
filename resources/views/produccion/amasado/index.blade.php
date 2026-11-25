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
                @can('manage-costs')
                    <form method="POST" action="{{ route('produccion.amasados.store') }}">
                        @csrf
                        <input type="hidden" name="fecha" value="{{ $date->toDateString() }}">
                        <button type="submit" class="w-full px-3 py-2 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors">
                            {{ $draft !== null ? 'Seguir amasado #'.$draft->number : 'Generar amasado' }}
                        </button>
                    </form>
                @endcan
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

            {{-- Amasados registrados --}}
            <section class="bg-superficie rounded-lg shadow">
                <header class="px-4 py-3 border-b border-miga flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-corteza">Amasados</h3>
                    @can('manage-costs')
                        <form method="POST" action="{{ route('produccion.amasados.store') }}">
                            @csrf
                            <input type="hidden" name="fecha" value="{{ $date->toDateString() }}">
                            <input type="hidden" name="manual" value="1">
                            <button type="submit" class="text-xs text-masa-madre hover:text-corteza underline">+ Amasado manual</button>
                        </form>
                    @endcan
                </header>
                @if($recentBatches->isEmpty())
                    <p class="px-4 py-4 text-sm text-masa-madre">Todavía no se registró ningún amasado.</p>
                @else
                    <ul class="divide-y divide-miga">
                        @foreach($recentBatches as $batch)
                            <li>
                                <a href="{{ route('produccion.amasados.show', $batch) }}"
                                    class="px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-harina transition-colors">
                                    <span class="text-sm text-corteza">
                                        <span class="font-medium">#{{ $batch->number }}</span>
                                        <span class="text-masa-madre">· {{ $batch->dough_date->format('d/m/Y') }}</span>
                                    </span>
                                    <span class="flex items-center gap-3">
                                        <span class="text-xs text-masa-madre font-mono">{{ \App\Services\QuantityFormatter::format($batch->totalUnits()) }} pzs</span>
                                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $batch->status->badgeClasses() }}">{{ $batch->status->label() }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

        </div>
    </div>
</x-app-layout>
