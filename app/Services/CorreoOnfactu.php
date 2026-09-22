<?php

namespace App\Services;

/**
 * Plantilla corporativa de correo de Onfactu, portada de email_template.js
 * (el del worker de emails), para que los avisos que salen de Laravel tengan
 * la misma cara que el correo de bienvenida: cabecera gris claro con el logo,
 * línea verde, cuerpo blanco, botón verde y pie con la web y el soporte.
 *
 * Existe otra copia en el portal de gestorías (lib/correo.php). Si se cambia
 * el diseño, cambiar las dos.
 */
class CorreoOnfactu
{
    /**
     * Logo de Onfactu Gestoría. PNG y no SVG: Gmail y Outlook no muestran SVG.
     */
    public const LOGO_GESTORIA = 'https://gestoria.onfactu.com/assets/logo-gestoria-email.png';

    private const ACCENT    = '#38d587';
    private const BTN_COLOR = '#070322';
    private const SITE_URL  = 'https://onfactu.com';

    /**
     * Párrafo con el estilo de la plantilla. Recibe HTML ya escapado.
     */
    public static function p(string $html): string
    {
        return '<p style="margin:0 0 16px;color:#374151;line-height:1.65;font-size:15px;'
             . 'font-family:system-ui,sans-serif;">' . $html . '</p>';
    }

    /**
     * HTML completo del correo.
     *
     * Opciones: titulo, cuerpo (HTML), boton_texto, boton_url, nota_pie, logo.
     */
    public static function html(array $o): string
    {
        $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $accent   = self::ACCENT;
        $btnColor = self::BTN_COLOR;
        $siteUrl  = self::SITE_URL;
        $logo     = $e($o['logo'] ?? self::LOGO_GESTORIA);

        $titulo = $o['titulo'] ?? '';
        $cuerpo = $o['cuerpo'] ?? '';
        $bt     = $o['boton_texto'] ?? '';
        $bu     = $o['boton_url'] ?? '';
        $pie    = $o['nota_pie'] ?? '';

        $tituloHtml = $titulo
            ? '<h1 style="margin:0 0 12px;font-size:22px;color:#070322;font-weight:700;font-family:system-ui,sans-serif;">'
              . $e($titulo) . '</h1>'
            : '';

        $botonHtml = '';
        if ($bt && $bu) {
            $botonHtml = '
      <table cellpadding="0" cellspacing="0" style="margin:28px 0 16px;">
        <tr>
          <td bgcolor="' . $accent . '" style="background:' . $accent . ';border-radius:10px;">
            <a href="' . $e($bu) . '" style="display:inline-block;padding:14px 32px;color:' . $btnColor . ';
               font-weight:700;font-size:15px;text-decoration:none;font-family:system-ui,sans-serif;">' . $e($bt) . '</a>
          </td>
        </tr>
      </table>
      <p style="margin:0 0 8px;color:#6b7280;font-size:13px;font-family:system-ui,sans-serif;">
        O copia y pega este enlace en tu navegador:
      </p>
      <p style="margin:0 0 24px;word-break:break-all;font-family:system-ui,sans-serif;">
        <a href="' . $e($bu) . '" style="color:' . $accent . ';font-size:13px;">' . $e($bu) . '</a>
      </p>';
        }

        $pieHtml = $pie
            ? '<p style="margin:0;color:#9ca3af;font-size:12px;border-top:1px solid #e5e7eb;padding-top:20px;'
              . 'font-family:system-ui,sans-serif;">' . $e($pie) . '</p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light only">
</head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:system-ui,-apple-system,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
         bgcolor="#f0f0f0" style="background:#f0f0f0;padding:32px 16px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" role="presentation"
             bgcolor="#ffffff" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:560px;width:100%;">
        <tr>
          <td bgcolor="#f5f5f5" style="background:#f5f5f5;padding:28px 40px;text-align:center;border-bottom:3px solid {$accent};">
            <img src="{$logo}" alt="Onfactu Gestoría" width="260"
                 style="display:block;margin:0 auto;max-width:260px;width:100%;height:auto;">
          </td>
        </tr>
        <tr>
          <td bgcolor="#ffffff" style="padding:36px 40px 28px;background:#ffffff;">
            {$tituloHtml}
            {$cuerpo}
            {$botonHtml}
            {$pieHtml}
          </td>
        </tr>
        <tr>
          <td bgcolor="#f5f5f5" style="background:#f5f5f5;padding:16px 40px;text-align:center;border-top:1px solid #e5e7eb;">
            <p style="margin:0;color:#9ca3af;font-size:12px;font-family:system-ui,sans-serif;">
              Onfactu &mdash;
              <a href="{$siteUrl}" style="color:#9ca3af;text-decoration:none;">{$siteUrl}</a>
              &mdash;
              <a href="mailto:soporte@onfactu.com" style="color:#9ca3af;text-decoration:none;">soporte@onfactu.com</a>
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
