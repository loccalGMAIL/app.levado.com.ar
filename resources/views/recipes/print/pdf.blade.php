<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['meta']['kind'] }} — {{ $sheet['meta']['name'] }}</title>
    @include('fixed-costs.report._styles')
    <style>
        @page {
            margin: 1.5cm 1.2cm;
        }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('recipes.print._document', ['sheet' => $sheet])
</body>
</html>
