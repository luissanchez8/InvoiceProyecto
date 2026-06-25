<?php

return [
    // Token compartido con el servidor Stripe. Se lee con config() (NO env())
    // para que sobreviva a `php artisan config:cache`. Con env() directo y la
    // config cacheada, env() devuelve null en runtime => 401 a toda notificación.
    'pro_api_token' => env('ONFACTU_PRO_API_TOKEN', ''),
];
