<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Borra del navegador las cookies antiguas que compartían todas las instancias.
 *
 * Hasta septiembre de 2026 las instancias creaban sus cookies de sesión y de
 * CSRF para todo ".onfactu.com" y con el mismo nombre. El navegador las enviaba
 * a todas: entrar en una instancia rompía la sesión en otra, y el acceso podía
 * fallar con "página caducada".
 *
 * Ahora cada instancia usa cookies solo para su subdominio y con nombre propio
 * (SESSION_DOMAIN y SESSION_COOKIE en su .env; ver tools/cookies_por_instancia.sh
 * en el repositorio de infraestructura). Aun así, los navegadores guardan las
 * antiguas hasta que caducan y las siguen enviando. La de CSRF iría primero y
 * haría fallar el acceso: por eso este middleware ordena borrarlas en cuanto
 * aparecen, y la primera página que se carga ya las limpia.
 *
 * Solo actúa en instancias ya migradas (SESSION_DOMAIN distinto del dominio
 * compartido). Se puede retirar cuando todas lo estén y las antiguas hayan
 * caducado en los navegadores.
 */
class PurgarCookiesCompartidas
{
    /** Nombres que usaban todas las instancias antes del cambio. */
    private const NOMBRES = ['onfactu_session', 'XSRF-TOKEN'];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        $partes = explode('.', $request->getHost());
        if (count($partes) < 3) {
            return $response; // sin subdominio: nada compartido que borrar
        }

        $compartido = implode('.', array_slice($partes, -2));
        if (ltrim((string) config('session.domain'), '.') === $compartido) {
            return $response; // instancia sin migrar: sus cookies siguen siendo esas
        }

        foreach (self::NOMBRES as $nombre) {
            if ($request->cookies->has($nombre)) {
                // Misma cookie (nombre, dominio y ruta) con fecha pasada: el navegador la borra.
                // No toca las nuevas, que van con otro dominio.
                $response->headers->setCookie(new Cookie($nombre, '', 1, '/', '.' . $compartido, true, false, false, 'lax'));
            }
        }

        return $response;
    }
}
