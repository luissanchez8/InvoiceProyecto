<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * The path to the "customer home" route for your application.
     *
     * This is used by Laravel authentication to redirect customers after login.
     *
     * @var string
     */
    public const CUSTOMER_HOME = '/customer/dashboard';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60);
        });

        // Onfactu v.1.17.2: respuesta del cliente desde /presupuesto/{token}.
        // Con "throttle:10,1" el contador era el del usuario con sesión
        // abierta, el mismo que gasta la API, y daba 429 al primer intento.
        // Aquí cuenta por IP y enlace, y al pasarse vuelve a la página con un
        // aviso en vez de una página de error.
        RateLimiter::for('presupuesto-publico', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip().'|'.$request->path())
                ->response(fn (Request $request) => redirect('/'.$request->path().'?espera=1'));
        });
    }
}
