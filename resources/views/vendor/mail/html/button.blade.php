{{--
  Onfactu v.1.17.0: botón de los correos en UNA sola línea y con bgcolor.
  Los correos pasan por Markdown: si el HTML del botón empieza con sangría de
  4 espacios o más, se enseña como código (le pasó al de "Ver y responder" del
  presupuesto en el nuevo Outlook). El relleno va en la celda y el color
  también como bgcolor, que es lo que respeta el Outlook de escritorio.
  Quien lo use tiene que llamarlo sin sangría (ver emails/send/*.blade.php).
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:28px auto 16px;"><tr><td align="center" bgcolor="#38d587" style="background:#38d587;border-radius:10px;padding:14px 32px;"><a href="{{ $url }}" target="_blank" style="display:inline-block;color:#070322;font-weight:700;font-size:15px;text-decoration:none;font-family:Arial,Helvetica,sans-serif;">{{ trim($slot) }}</a></td></tr></table>
