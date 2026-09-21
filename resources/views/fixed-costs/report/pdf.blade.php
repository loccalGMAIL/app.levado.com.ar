<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de gastos — {{ $report['meta']['from'] }} al {{ $report['meta']['to'] }}</title>
    @include('fixed-costs.report._styles')
    <style>
        @page {
            margin: 1.5cm 1.2cm;
        }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('fixed-costs.report._document', ['report' => $report])
</body>
</html>
