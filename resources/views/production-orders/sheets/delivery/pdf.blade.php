<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Planilla de reparto — {{ $sheet['meta']['number'] }}</title>
    @include('fixed-costs.report._styles')
    @include('production-orders.sheets._styles')
    <style>
        @page {
            margin: 1.5cm 1.2cm;
        }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('production-orders.sheets.delivery._document', ['sheet' => $sheet])
</body>
</html>
