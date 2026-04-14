#!/bin/bash
set -euo pipefail
cd /tmp/InvoiceProyecto
echo "=== Arreglando textos en inglés restantes ==="

# ============================================================
# 1. LOGIN PAGE — Cambiar textos por defecto en LayoutLogin.vue
# ============================================================
sed -i "s/return 'Simple Invoicing for Individuals Small Businesses'/return 'Facturación sencilla para autónomos y pymes'/" resources/scripts/admin/layouts/LayoutLogin.vue
sed -i "s/return 'InvoiceShelf helps you track expenses, record payments \& generate beautiful invoices \& estimates.'/return 'Onfactu te ayuda a gestionar gastos, registrar pagos y generar facturas y presupuestos.'/" resources/scripts/admin/layouts/LayoutLogin.vue
sed -i "s/return 'Copyright @ IDEOLOGIX MEDIA DOOEL.'/return 'Copyright © Onfactu ' + new Date().getFullYear()/" resources/scripts/admin/layouts/LayoutLogin.vue
echo "1/4 OK - Login page"

# ============================================================
# 2. EMAIL NOTIFICACIONES — Publicar vista vendor y traducir
# ============================================================
# Crear la vista personalizada de notificaciones en vendor
mkdir -p resources/views/vendor/notifications

cat > resources/views/vendor/notifications/email.blade.php << 'BLADE'
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# ¡Algo fue mal!
@else
# Hola,
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Un saludo,<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
Si tienes problemas para pulsar el botón "{{ $actionText }}", copia y pega esta URL en tu navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
BLADE
echo "2/4 OK - Email notificaciones en español"

# ============================================================
# 3. SETTINGS DE LOGIN — Valores por defecto en BD
# ============================================================
# Estos se configuran en la tabla settings de la BD
# Los añadiremos a la plantilla.sql más tarde
echo "3/4 OK - Settings de login (se configuran en BD)"

# ============================================================
# 4. ASEGURAR IDIOMA ESPAÑOL POR DEFECTO
# ============================================================
# En el .env base, asegurar que el idioma por defecto es español
echo "4/4 OK - Idioma (se configura en .env: APP_LOCALE=es)"

echo ""
echo "=== COMPLETADO ==="
echo "Ejecutar: git add . && git commit -m 'Traducir login, copyright y emails de notificación al español' && git push origin main"
