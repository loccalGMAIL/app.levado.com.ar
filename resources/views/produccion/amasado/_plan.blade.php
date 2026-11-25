{{--
    Las tres partes de la planilla de amasado en pantalla: va a cámara,
    directo y masas. Recibe $plan de App\Services\DoughPlanner::forDate().
--}}
@php
    $qty = fn (float $value) => \App\Services\QuantityFormatter::format($value);
    $sections = [
        ['rows' => $plan['chamber'], 'title' => 'Va a cámara', 'note' => 'para mañana '.$plan['chamber_date']->format('d/m'), 'chamber' => true],
        ['rows' => $plan['direct'], 'title' => 'Directo', 'note' => 'para hoy '.$plan['date']->format('d/m'), 'chamber' => false],
    ];
@endphp

@if(! empty($plan['chamber']) || $plan['last_closing'] !== null)
    @if($plan['last_closing'] !== null && $plan['last_closing']['same_day'])
        <p class="text-xs text-masa-madre">Último cierre de cámara: {{ $plan['last_closing']['at']->isToday() ? 'hoy' : $plan['last_closing']['at']->format('d/m') }} {{ $plan['last_closing']['at']->format('H:i') }}.</p>
    @else
        <p class="text-sm text-amber-800 bg-amber-50 rounded-md px-4 py-2">
            La cámara no se cerró {{ $plan['date']->isToday() ? 'hoy' : 'el '.$plan['date']->format('d/m') }}: lo «en cámara» puede estar desactualizado.
            @if($plan['last_closing'] !== null)
                Último cierre: {{ $plan['last_closing']['at']->format('d/m H:i') }}.
            @endif
        </p>
    @endif
@endif

@foreach($sections as $section)
    <section class="bg-superficie rounded-lg shadow">
        <header class="px-4 py-3 border-b border-miga flex items-baseline justify-between gap-2">
            <h3 class="text-sm font-semibold text-corteza">{{ $section['title'] }}</h3>
            <span class="text-xs text-masa-madre">{{ $section['note'] }}</span>
        </header>

        @if(empty($section['rows']))
            <p class="px-4 py-4 text-sm text-masa-madre">Nada pedido.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-masa-madre text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">Artículo</th>
                            <th class="px-3 py-2 font-medium text-right">Pedido</th>
                            @if($section['chamber'])
                                <th class="px-3 py-2 font-medium text-right">En cámara</th>
                            @endif
                            <th class="px-4 py-2 font-medium text-right">A amasar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($section['rows'] as $row)
                            <tr>
                                <td class="px-4 py-2 text-corteza">{{ $row['name'] }}</td>
                                <td class="px-3 py-2 text-right font-mono text-masa-madre whitespace-nowrap">{{ $qty($row['ordered']) }} {{ $row['unit'] }}</td>
                                @if($section['chamber'])
                                    <td class="px-3 py-2 text-right font-mono text-masa-madre whitespace-nowrap">{{ $qty($row['in_chamber']) }}</td>
                                @endif
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    @if($row['containers'] !== null)
                                        <span class="font-mono font-semibold text-corteza">{{ $qty($row['containers']) }} {{ $row['container']['short'] }}</span>
                                        <span class="block text-xs text-masa-madre font-mono">{{ $qty($row['to_make']) }} {{ $row['unit'] }}</span>
                                    @else
                                        <span class="font-mono font-semibold text-corteza">{{ $qty($row['to_make']) }} {{ $row['unit'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endforeach

<section class="bg-superficie rounded-lg shadow">
    <header class="px-4 py-3 border-b border-miga">
        <h3 class="text-sm font-semibold text-corteza">Masas a preparar</h3>
    </header>

    @if(empty($plan['masses']) && empty($plan['without_mass']))
        <p class="px-4 py-4 text-sm text-masa-madre">Nada para amasar.</p>
    @else
        <ul class="divide-y divide-miga">
            @foreach($plan['masses'] as $mass)
                <li class="px-4 py-2 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-corteza font-medium">{{ $mass['name'] }}</p>
                        <p class="text-xs text-masa-madre">Para: {{ implode(', ', $mass['articles']) }}</p>
                    </div>
                    <span class="font-mono font-semibold text-corteza whitespace-nowrap">{{ $qty($mass['quantity']) }} {{ $mass['unit'] }}</span>
                </li>
            @endforeach
            @if(! empty($plan['without_mass']))
                <li class="px-4 py-2">
                    <p class="text-sm text-corteza font-medium">Sin masa común</p>
                    <p class="text-xs text-masa-madre">{{ implode(', ', $plan['without_mass']) }}</p>
                </li>
            @endif
        </ul>
    @endif
</section>
