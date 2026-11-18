<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['title'] }}</title>
    @include('fixed-costs.report._styles')
    <style>
        @page {
            margin: 1.2cm 1cm;
        }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('reparto.print._document', ['sheet' => $sheet])
</body>
</html>
