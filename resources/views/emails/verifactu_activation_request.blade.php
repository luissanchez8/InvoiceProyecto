<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud activación VeriFactu</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #000; border-bottom: 2px solid #38d587; padding-bottom: 10px;">
        Nueva solicitud de activación de VeriFactu
    </h2>

    <p>Un usuario ha solicitado la activación de VeriFactu para su cuenta.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee; width: 40%;"><strong>ID de solicitud</strong></td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">#{{ $requestId }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Usuario</strong></td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $user->name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Email del usuario</strong></td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
            </td>
        </tr>
        @if ($company)
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Empresa</strong></td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $company->name ?? '—' }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Subdominio / instancia</strong></td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                <a href="{{ $subdomain }}">{{ $subdomain }}</a>
            </td>
        </tr>
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Fecha de solicitud</strong></td>
            <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <p style="margin-top: 30px;">
        <strong>Próximos pasos:</strong> contactar con el cliente para recoger la información
        necesaria (certificado digital, etc.) y, una vez todo en orden, activar VeriFactu
        desde el panel de Asistencia de esta instancia
        (Ajustes → Configuración App → toggle <code>OPCION_VERIFACTU</code>).
    </p>

    <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
    <p style="font-size: 12px; color: #888;">
        Email enviado automáticamente por Onfactu. No respondas a este mensaje,
        responde directamente a {{ $user->email }}.
    </p>
</body>
</html>
