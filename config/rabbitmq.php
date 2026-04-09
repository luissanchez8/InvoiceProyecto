<?php

return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'verifactu_queue' => env('RABBITMQ_VERIFACTU_QUEUE', 'verifactu.facturas'),
    'verifactu_webhook_token' => env('VERIFACTU_WEBHOOK_TOKEN', ''),
];
