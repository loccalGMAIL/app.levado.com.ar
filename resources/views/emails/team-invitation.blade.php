<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación a {{ $tenantName }}</title>
</head>
<body style="font-family: sans-serif; background: #FAF7F2; margin: 0; padding: 40px 0;">
    <div style="max-width: 520px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px;">
        <h1 style="font-size: 22px; color: #3D2B1F; margin-top: 0;">
            Te invitaron a {{ $tenantName }}
        </h1>

        <p style="color: #6B5B45;">
            Recibiste una invitación para unirte al equipo de <strong>{{ $tenantName }}</strong>
            en Levado con el rol <strong>{{ $role }}</strong>.
        </p>

        @if(!empty($customMessage))
        <p style="color: #6B5B45; border-left: 3px solid #C8622A; padding-left: 12px; margin: 16px 0;">
            {{ $customMessage }}
        </p>
        @endif

        <p style="margin: 32px 0;">
            <a href="{{ $acceptUrl }}"
               style="background: #C8622A; color: #fff; text-decoration: none;
                      padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;">
                Aceptar invitación
            </a>
        </p>

        <p style="color: #9c897a; font-size: 14px;">
            Este link expira el {{ $expiresAt }}. Si no esperabas esta invitación, podés ignorar este correo.
        </p>

        <hr style="border: none; border-top: 1px solid #F2EAD8; margin: 32px 0;">
        <p style="color: #9c897a; font-size: 12px; margin: 0;">Levado — Sistema de costos para panaderías</p>
    </div>
</body>
</html>
