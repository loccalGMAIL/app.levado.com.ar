{{--
    Complemento de fixed-costs.report._styles para las planillas de orden de
    producción: encabezado simple (sin columna de filtros), bloque por grupo
    de reparto con salto de página entre repartidores/sucursales, renglón de
    pedido compacto (número — destino — dirección en una sola línea) y el
    total a llevar en un tono distinto para separarlo de los pedidos. Misma
    raíz .levado-report -mismas reglas de compatibilidad con dompdf (sin
    flex/grid/variables)-.
--}}
<style>
    {{-- Encabezado en una sola fila (logo/negocio a la izquierda, título y
         datos a la derecha, como fixed-costs.report) para ocupar menos alto
         de hoja. white-space: nowrap para que ni el título ("Planilla de
         producción — Orden #N") ni "Tipo: ... · Fecha: ... · Emitido: ..." se
         partan en dos líneas; font-size más chico que el 19px de
         fixed-costs.report porque acá el título es más largo y la columna,
         angosta (35/65 en vez de 60/40). --}}
    .levado-report .report-title { font-size: 15px; white-space: nowrap; }
    .levado-report .sheet-meta { font-size: 10px; color: #6B5B45; text-align: right; margin: 4px 0 0; white-space: nowrap; }

    .levado-report .group-block { page-break-inside: avoid; }
    .levado-report .group-block + .group-block { page-break-before: always; }
    .levado-report .group-title { font-size: 15px; font-weight: 700; color: #3D2B1F; margin: 0; }
    .levado-report .group-subtitle { font-size: 10px; color: #6B5B45; margin: 2px 0 8px; }

    .levado-report .request-block { margin-bottom: 3px; page-break-inside: avoid; }
    .levado-report .request-line { background: #F2EAD8; padding: 3px 8px; font-size: 10.5px; color: #3D2B1F; }
    .levado-report .request-line .request-number { font-weight: 700; color: #6B5B45; }
    .levado-report .request-note { font-size: 9px; color: #6B5B45; padding: 1px 8px 0; }

    .levado-report .request-block table.data td { padding: 2px 8px; border-bottom: none; }

    {{-- Columna de cantidad a ancho fijo en ambas tablas -renglones de pedido
         y total-, para que las cantidades queden alineadas entre sí: con
         table-layout: auto cada tabla acomoda su columna según el largo del
         nombre de producto que le toque, y las cantidades no coinciden. --}}
    .levado-report .request-block table.data,
    .levado-report table.delivery-totals {
        table-layout: fixed;
    }
    .levado-report .request-block table.data td:last-child,
    .levado-report table.delivery-totals th:last-child,
    .levado-report table.delivery-totals td:last-child {
        width: 90px;
    }

    .levado-report table.delivery-totals { margin: 4px 0 14px; border: 1px solid #6B5B45; }
    .levado-report table.delivery-totals th, .levado-report table.delivery-totals td { padding: 2px 8px; }
    .levado-report table.delivery-totals thead th { background: #6B5B45; color: #FAF7F2; border-bottom: none; }
    .levado-report table.delivery-totals tbody td { background: #FAF7F2; font-weight: 700; color: #3D2B1F; border-bottom: 1px solid #E4D9C0; }
    .levado-report table.delivery-totals tbody tr:last-child td { border-bottom: none; }

    .levado-report .shortfall-row td { color: #B91C1C; font-weight: 700; }
</style>
