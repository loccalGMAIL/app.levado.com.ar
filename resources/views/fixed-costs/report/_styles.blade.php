{{--
    CSS vanilla del reporte, compartido por pantalla (screen.blade.php) y PDF
    (pdf.blade.php). Todo bajo la raíz .levado-report y con cada propiedad
    declarada explícita -sin apoyarse en estilos por defecto del navegador-
    para no depender de qué reset (Tailwind preflight en pantalla, ninguno en
    dompdf) esté activo alrededor. dompdf no soporta flex/grid ni variables
    CSS: sólo tablas, bloques y colores planos.

    Paleta calcada de tailwind.config.js (no se puede usar Tailwind acá).
--}}
<style>
    .levado-report {
        font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
        font-size: 11px;
        line-height: 1.45;
        color: #3D2B1F;
    }
    .levado-report * { box-sizing: border-box; }

    .levado-report .header-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .levado-report .header-table td { border: none; padding: 0; vertical-align: top; }
    .levado-report .logo { max-width: 130px; max-height: 60px; }
    .levado-report .business-name { font-size: 17px; font-weight: 700; color: #3D2B1F; margin: 0; }
    .levado-report .business-meta { font-size: 10px; color: #6B5B45; margin: 2px 0 0; }
    .levado-report .report-title { font-size: 19px; font-weight: 700; color: #3D2B1F; text-align: right; margin: 0; }
    .levado-report .report-meta { font-size: 10px; color: #6B5B45; text-align: right; margin: 4px 0 0; }

    .levado-report .section { margin-bottom: 20px; page-break-inside: avoid; }
    .levado-report .section-title {
        font-size: 13px; font-weight: 700; color: #3D2B1F;
        margin: 0 0 8px; padding-bottom: 5px; border-bottom: 2px solid #3D2B1F;
    }

    .levado-report table.data { width: 100%; border-collapse: collapse; }
    .levado-report table.data th,
    .levado-report table.data td {
        padding: 6px 8px; text-align: left; border-bottom: 1px solid #F2EAD8; font-size: 11px;
    }
    .levado-report table.data thead th {
        background: #F2EAD8; color: #6B5B45; font-weight: 600; border-bottom: 1px solid #F2EAD8;
    }
    .levado-report table.data tfoot td {
        border-top: 2px solid #F2EAD8; border-bottom: none; font-weight: 700; color: #3D2B1F;
    }
    .levado-report .text-right { text-align: right; }
    .levado-report .amount { font-family: 'DejaVu Sans Mono', monospace; }
    .levado-report .muted { color: #6B5B45; }
    .levado-report .inactive-row td { color: #6B5B45; font-style: italic; }
    .levado-report .up { color: #B91C1C; }
    .levado-report .down { color: #15803D; }

    .levado-report .detail-block { margin-bottom: 14px; page-break-inside: avoid; }
    .levado-report .detail-name { font-weight: 700; color: #3D2B1F; }
    .levado-report .detail-category { font-size: 10px; color: #6B5B45; margin: 0 0 4px; }

    .levado-report .totals-table { width: 60%; margin-left: auto; border-collapse: collapse; }
    .levado-report .totals-table td { border: none; padding: 3px 0; font-size: 11px; }
    .levado-report .totals-table .grand td {
        border-top: 2px solid #3D2B1F; font-weight: 700; font-size: 13px; padding-top: 7px;
    }

    .levado-report .empty {
        padding: 18px; text-align: center; color: #6B5B45; font-size: 11px;
        background: #FAF7F2; border: 1px solid #F2EAD8;
    }
    .levado-report .footnote { font-size: 9px; color: #6B5B45; margin-top: 5px; }
</style>
