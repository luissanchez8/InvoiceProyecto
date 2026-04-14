<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light only">
  <meta name="supported-color-schemes" content="light only">
  <style>
    body { margin:0; padding:0; background:#f0f0f0; font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; }
    .wrapper { background:#f0f0f0; padding:32px 16px; }
    .content { background:#ffffff; border-radius:12px; overflow:hidden; max-width:560px; width:100%; }
    .header-cell { background:#f5f5f5; padding:28px 40px; text-align:center; border-bottom:3px solid #38d587; }
    .body-cell { padding:36px 40px 28px; background:#ffffff; font-size:15px; color:#070322; line-height:1.6; }
    .body-cell p { margin:0 0 12px; font-size:15px; color:#070322; }
    .footer-cell { background:#f5f5f5; padding:16px 40px; text-align:center; border-top:1px solid #e5e7eb; }
    @media only screen and (max-width: 600px) {
      .content { width:100% !important; }
      .body-cell, .header-cell, .footer-cell { padding-left:20px !important; padding-right:20px !important; }
    }
  </style>
</head>
<body>
  <table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#f0f0f0">
    <tr><td align="center">
      <table class="content" width="560" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#ffffff">
        <tr><td class="header-cell">{{ $header ?? '' }}</td></tr>
        <tr><td class="body-cell">{{ Illuminate\Mail\Markdown::parse($slot) }}{{ $subcopy ?? '' }}</td></tr>
        <tr><td class="footer-cell">{{ $footer ?? '' }}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
