<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido/a a {{ $tenantName }}</title>
</head>
<body style="font-family: sans-serif; background: #FAF7F2; margin: 0; padding: 40px 0;">
    <div style="max-width: 520px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 40px;">
        <div style="margin-bottom: 28px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="60 40 360 90" aria-label="Levado" style="height: 36px; width: auto;">
                <text x="240" y="105" font-family="Georgia, 'Times New Roman', serif" font-size="84" font-weight="500" text-anchor="middle" fill="#3D2B1F" letter-spacing="-2">levado</text>
                <path d="M 175 122 Q 240 130 305 122" stroke="#C8622A" stroke-width="2" fill="none" stroke-linecap="round" opacity="0.85"/>
            </svg>
        </div>

        <h1 style="font-size: 22px; color: #3D2B1F; margin-top: 0;">
            ¡Hola, {{ $userName }}!
        </h1>

        <p style="color: #6B5B45;">
            Tu cuenta en <strong>Levado</strong> fue creada con éxito y ya sos parte
            del equipo de <strong>{{ $tenantName }}</strong>.
        </p>

        <p style="color: #6B5B45;">
            Desde ahora podés ingresar cuando quieras y empezar a gestionar
            los costos de tu panadería.
        </p>

        <p style="margin: 32px 0;">
            <a href="{{ $loginUrl }}"
               style="background: #C8622A; color: #fff; text-decoration: none;
                      padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;">
                Ingresar a Levado
            </a>
        </p>

        <hr style="border: none; border-top: 1px solid #F2EAD8; margin: 32px 0;">
        <p style="color: #9c897a; font-size: 12px; margin: 0;">Sistema de costos para panaderías</p>
    </div>
</body>
</html>
