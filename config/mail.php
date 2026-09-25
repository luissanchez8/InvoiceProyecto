<?php
return [
    'driver' => env('MAIL_DRIVER', 'smtp'),
    'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
    'port' => env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
    'sendmail' => '/usr/sbin/sendmail -bs',
    'log_channel' => env('MAIL_LOG_CHANNEL'),
    // Onfactu v.1.14: verificar el certificado del servidor de correo. Siempre,
    // salvo con el antiguo de Furanet, que no lo tenía bien.
    'verificar_certificado' => env('MAIL_VERIFY_PEER', env('MAIL_HOST') !== 'mail.onfactu.com'),
    'stream' => [
        'ssl' => [
            'allow_self_signed' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ],
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
            'stream' => [
                'ssl' => [
                    'allow_self_signed' => ! env('MAIL_VERIFY_PEER', env('MAIL_HOST') !== 'mail.onfactu.com'),
                    'verify_peer' => env('MAIL_VERIFY_PEER', env('MAIL_HOST') !== 'mail.onfactu.com'),
                    'verify_peer_name' => env('MAIL_VERIFY_PEER', env('MAIL_HOST') !== 'mail.onfactu.com'),
                ],
            ],
        ],
    ],
];
