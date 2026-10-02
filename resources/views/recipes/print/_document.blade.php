{{--
    Contenido de la receta imprimible, compartido por pantalla y PDF. Recibe
    sólo el array $sheet armado por App\Services\RecipePrintSheet::for() -ni
    un modelo Eloquent- para no arriesgar un lazy load dentro del render de
    dompdf. Sin costos ni precios: es la hoja para quien produce.
--}}
<div class="levado-report">

    <table class="header-table">
        <tr>
            <td style="width: 35%;">
                @if($sheet['business']['logo'])
                    <img src="{{ $sheet['business']['logo'] }}" alt="{{ $sheet['business']['name'] }}" class="logo"><br>
                @endif
                <p class="business-name">{{ $sheet['business']['razon_social'] ?? $sheet['business']['name'] }}</p>
                @if($sheet['business']['cuit'])
                    <p class="business-meta">CUIT {{ $sheet['business']['cuit'] }}</p>
                @endif
            </td>
            <td style="width: 65%;">
                <p class="report-title" style="font-size: 15px;">{{ $sheet['meta']['kind'] }} — {{ $sheet['meta']['name'] }}</p>
                <p class="report-meta">
                    Rendimiento: {{ $sheet['meta']['yield'] }} · Emitido: {{ $sheet['meta']['generated_at'] }}
                </p>
            </td>
        </tr>
    </table>

    @if($sheet['meta']['description'])
        <div class="section">
            <p class="section-title">Descripción</p>
            <p style="margin: 0;">{!! nl2br(e($sheet['meta']['description'])) !!}</p>
        </div>
    @endif

    @if(empty($sheet['ingredients']) && empty($sheet['subrecipes']) && empty($sheet['packagings']) && empty($sheet['labor']))
        <div class="empty">Esta receta todavía no tiene componentes.</div>
    @endif

    @if(! empty($sheet['ingredients']))
        <div class="section">
            <p class="section-title">Ingredientes</p>
            <table class="data">
                <thead>
                    <tr><th>Ingrediente</th><th class="text-right">Cantidad</th></tr>
                </thead>
                <tbody>
                    @foreach($sheet['ingredients'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right amount">{{ number_format($row['quantity'], 3, ',', '.') }} {{ $row['unit'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(! empty($sheet['subrecipes']))
        <div class="section">
            <p class="section-title">Sub-recetas</p>
            <table class="data">
                <thead>
                    <tr><th>Sub-receta</th><th class="text-right">Cantidad</th></tr>
                </thead>
                <tbody>
                    @foreach($sheet['subrecipes'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right amount">{{ number_format($row['quantity'], 3, ',', '.') }} {{ $row['unit'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(! empty($sheet['packagings']))
        <div class="section">
            <p class="section-title">Envases</p>
            <table class="data">
                <thead>
                    <tr><th>Envase</th><th class="text-right">Cantidad</th></tr>
                </thead>
                <tbody>
                    @foreach($sheet['packagings'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right amount">{{ number_format($row['quantity'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(! empty($sheet['labor']))
        <div class="section">
            <p class="section-title">Mano de obra</p>
            <table class="data">
                <thead>
                    <tr><th>Tarea</th><th class="text-right">Horas</th></tr>
                </thead>
                <tbody>
                    @foreach($sheet['labor'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right amount">{{ number_format($row['hours'], 2, ',', '.') }} h</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
