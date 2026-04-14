#!/bin/bash
set -euo pipefail
cd /tmp/InvoiceProyecto
echo "=== Traduciendo emails y cambiando InvoiceShelf → Onfactu ==="

# ============================================================
# 1. RESET PASSWORD — Traducir al español
# ============================================================
cat > app/Notifications/MailResetPasswordNotification.php << 'PHPEOF'
<?php
namespace App\Notifications;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class MailResetPasswordNotification extends ResetPassword
{
    use Queueable;
    public function __construct($token)
    {
        parent::__construct($token);
    }
    public function via($notifiable): array
    {
        return ['mail'];
    }
    public function toMail($notifiable): MailMessage
    {
        $link = url('/reset-password/'.$this->token);
        return (new MailMessage)
            ->subject('Restablecer contraseña - Onfactu')
            ->line('Has recibido este correo porque se ha solicitado restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $link)
            ->line('Este enlace caducará en '.config('auth.passwords.users.expire').' minutos.')
            ->line('Si no has solicitado restablecer tu contraseña, no es necesario realizar ninguna acción.');
    }
    public function toArray($notifiable): array
    {
        return [];
    }
}
PHPEOF

cat > app/Notifications/CustomerMailResetPasswordNotification.php << 'PHPEOF'
<?php
namespace App\Notifications;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class CustomerMailResetPasswordNotification extends ResetPassword
{
    use Queueable;
    public function __construct($token)
    {
        parent::__construct($token);
    }
    public function via($notifiable): array
    {
        return ['mail'];
    }
    public function toMail($notifiable): MailMessage
    {
        $link = url("/{$notifiable->company->slug}/customer/reset/password/".$this->token);
        return (new MailMessage)
            ->subject('Restablecer contraseña - Onfactu')
            ->line('Has recibido este correo porque se ha solicitado restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $link)
            ->line('Este enlace caducará en '.config('auth.passwords.users.expire').' minutos.')
            ->line('Si no has solicitado restablecer tu contraseña, no es necesario realizar ninguna acción.');
    }
    public function toArray($notifiable): array
    {
        return [];
    }
}
PHPEOF
echo "1/5 OK - Reset password traducido"

# ============================================================
# 2. TEXTOS POR DEFECTO DE EMAILS (Company.php)
# ============================================================
sed -i "s/You have received a new invoice from <b>{COMPANY_NAME}<\/b>.<\/br> Please download using the button below:/Ha recibido una nueva factura de <b>{COMPANY_NAME}<\/b>./" app/Models/Company.php
sed -i "s/You have received a new estimate from <b>{COMPANY_NAME}<\/b>.<\/br> Please download using the button below:/Ha recibido un nuevo presupuesto de <b>{COMPANY_NAME}<\/b>./" app/Models/Company.php
sed -i "s/Thank you for the payment.<\/b><\/br> Please download your payment receipt using the button below:/Gracias por el pago. Puede descargar el recibo adjunto./" app/Models/Company.php
echo "2/5 OK - Textos por defecto de emails"

# ============================================================
# 3. TEST EMAIL
# ============================================================
cat > resources/views/emails/test.blade.php << 'BLADE'
@component('mail::message')
# Email de prueba de Onfactu

{{ $my_message }}

@endcomponent
BLADE
echo "3/5 OK - Test email"

# ============================================================
# 4. VIEWED EMAILS — Usan mail::message (plantilla por defecto de Laravel)
#    Cambiar config('app.name') por 'Onfactu' y usar layout corporativo
# ============================================================
cat > resources/views/emails/viewed/invoice.blade.php << 'BLADE'
@component('mail::layout')
    @slot('header')
        @component('mail::header', ['url' => ''])
        Onfactu
        @endcomponent
    @endslot

    @lang('mail_viewed_invoice', ['name' => $data['user']['name']])

    @slot('subcopy')
        @component('mail::subcopy')
            @component('mail::button', ['url' => url('/admin/invoices/'.$data['invoice']['id'].'/view')])
                @lang('mail_view_invoice')
            @endcomponent
        @endcomponent
    @endslot

    @slot('footer')
        @component('mail::footer')
            Creado por <a href="https://onfactu.com/" target="_blank" style="color:#38d587;text-decoration:none;font-weight:700;">onfactu</a>
        @endcomponent
    @endslot
@endcomponent
BLADE

cat > resources/views/emails/viewed/estimate.blade.php << 'BLADE'
@component('mail::layout')
    @slot('header')
        @component('mail::header', ['url' => ''])
        Onfactu
        @endcomponent
    @endslot

    @lang('mail_viewed_estimate', ['name' => $data['user']['name']])

    @slot('subcopy')
        @component('mail::subcopy')
            @component('mail::button', ['url' => url('/admin/estimates/'.$data['estimate']['id'].'/view')])
                @lang('mail_view_estimate')
            @endcomponent
        @endcomponent
    @endslot

    @slot('footer')
        @component('mail::footer')
            Creado por <a href="https://onfactu.com/" target="_blank" style="color:#38d587;text-decoration:none;font-weight:700;">onfactu</a>
        @endcomponent
    @endslot
@endcomponent
BLADE
echo "4/5 OK - Viewed emails con layout corporativo"

# ============================================================
# 5. CAMBIAR InvoiceShelf → Onfactu en es.json
# ============================================================
sed -i 's/InvoiceShelf/Onfactu/g' lang/es.json
echo "5/5 OK - InvoiceShelf → Onfactu en es.json"

echo ""
echo "=== COMPLETADO ==="
echo "Ejecutar: git add . && git commit -m 'Traducir emails al español + InvoiceShelf → Onfactu' && git push origin main"
