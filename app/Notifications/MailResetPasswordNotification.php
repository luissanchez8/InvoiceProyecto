<?php
namespace App\Notifications;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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
