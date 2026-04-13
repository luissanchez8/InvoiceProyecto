<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class MailStreamServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->afterResolving('mailer', function ($mailer) {
            try {
                $transport = $mailer->getSymfonyTransport();
                if ($transport instanceof EsmtpTransport) {
                    $transport->getStream()->setStreamOptions([
                        'ssl' => [
                            'allow_self_signed' => true,
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                // Silently ignore
            }
        });
    }

    public function register(): void {}
}
