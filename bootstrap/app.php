<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \Lavary\Menu\ServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->validateCsrfTokens(except: [
            'login',
        ]);

        $middleware->append([
            \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
            \App\Http\Middleware\TrimStrings::class,
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\ConfigMiddleware::class,
        ]);

        $middleware->web([
            \App\Http\Middleware\EncryptCookies::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $middleware->statefulApi();
        $middleware->throttleApi('180,1');

        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\TrustProxies::class);

        $middleware->replaceInGroup('web', \Illuminate\Cookie\Middleware\EncryptCookies::class, \App\Http\Middleware\EncryptCookies::class);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'bouncer' => \App\Http\Middleware\ScopeBouncer::class,
            'check-menu' => \App\Http\Middleware\CheckMenuOption::class,
            'company' => \App\Http\Middleware\CompanyMiddleware::class,
            'check-plan-status' => \App\Http\Middleware\CheckPlanStatus::class,
            'cron-job' => \App\Http\Middleware\CronJobMiddleware::class,
            'customer' => \App\Http\Middleware\CustomerRedirectIfAuthenticated::class,
            'customer-guest' => \App\Http\Middleware\CustomerGuest::class,
            'customer-portal' => \App\Http\Middleware\CustomerPortalMiddleware::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'install' => \App\Http\Middleware\InstallationMiddleware::class,
            'pdf-auth' => \App\Http\Middleware\PdfMiddleware::class,
            'redirect-if-installed' => \App\Http\Middleware\RedirectIfInstalled::class,
            'redirect-if-unauthenticated' => \App\Http\Middleware\RedirectIfUnauthorized::class,
        ]);

        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\Authenticate::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Onfactu v.1.9.5: Capturar errores SQL/DB para no exponer detalles
        // técnicos al usuario. En lugar del SQLSTATE crudo, devolver un mensaje
        // amable. Solo aplica a peticiones JSON/API (frontend SPA).
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $code = $e->getCode();
                $message = 'Se ha producido un error al guardar los datos. Revisa los campos e inténtalo de nuevo.';

                // Detectar errores comunes para mensajes más específicos
                $raw = (string) $e->getMessage();
                if (str_contains($raw, 'value too long') || str_contains($raw, 'String data, right truncated')) {
                    $message = 'Uno de los campos supera la longitud permitida. Acorta el texto e inténtalo de nuevo.';
                } elseif (str_contains($raw, 'Duplicate entry') || str_contains($raw, 'duplicate key value')) {
                    $message = 'Ya existe un registro con esos datos.';
                } elseif (str_contains($raw, 'foreign key constraint')) {
                    $message = 'No se puede completar la operación porque el registro está en uso.';
                } elseif (str_contains($raw, 'cannot be null') || str_contains($raw, 'null value in column')) {
                    $message = 'Falta rellenar un campo obligatorio.';
                }

                // Log del error real para que el administrador lo vea
                \Illuminate\Support\Facades\Log::error('QueryException oculta al usuario', [
                    'message' => $raw,
                    'sql' => $e->getSql() ?? null,
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => $message,
                    'error' => true,
                ], 422);
            }
            // Si no es JSON, comportamiento por defecto
            return null;
        });
    })->create();
