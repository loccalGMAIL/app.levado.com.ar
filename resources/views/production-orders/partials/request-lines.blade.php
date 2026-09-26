{{--
    Líneas de un pedido (artículo + cantidad). Dos ramas genuinamente
    distintas — no una sola con inputs deshabilitados:
    - $showGrid: grilla editable (picker + cantidad editable + guardado en
      lote vía ProductionOrderLineController::sync()). El picker+tabla vive
      en partials.lines-grid, compartido con el modal de alta.
    - si no: tabla de sólo lectura, server-side (a propósito: assertSee()
      sobre un nombre con acentos no es confiable contra un x-for — Js::from()
      los escapa \uXXXX — así que la rama de lectura se queda renderizada por
      Blade, no por Alpine).

    $showGrid ya combina isEditable() + el permiso manage-costs (ver
    show.blade.php): un viewer, aunque la orden sea editable, ve sólo lectura.
--}}
@if($showGrid)
    <div x-data="productionOrderLines({
            saveUrl: @js(route('production-orders.requests.lines.sync', [$productionOrder, $request])),
            previousUrl: @js(route('production-orders.requests.previous-lines', [$productionOrder, $request])),
            products: products,
            lines: @js($request->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'name' => $line->product->name ?? '—',
                'unit' => $line->unit->short(),
                'quantity' => (float) $line->quantity,
            ])),
        })"
        class="border-t border-miga">
        @include('production-orders.partials.lines-grid', ['pickerId' => "lines-picker-{$request->id}", 'products' => $products])
    </div>
@elseif($request->lines->isNotEmpty())
    <table class="w-full text-sm">
        <tbody class="divide-y divide-miga">
            @foreach($request->lines as $line)
                <tr>
                    <td class="px-5 py-2 text-corteza">{{ $line->product?->name ?? '—' }}</td>
                    <td class="px-5 py-2 text-right font-mono text-corteza">
                        {{ number_format($line->quantity, 2, ',', '.') }} {{ $line->unit->short() }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
