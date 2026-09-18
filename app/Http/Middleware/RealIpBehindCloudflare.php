<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Onfactu — IP real del visitante detrás de Cloudflare.
 *
 * Todas las instancias van detrás de Cloudflare, que pone SU propia IP en
 * X-Forwarded-For y deja la del visitante en la cabecera CF-Connecting-IP.
 * Laravel no conoce esa cabecera, así que $request->ip() devolvía la de
 * Cloudflare (104.x, 162.158.x, 172.67.x...) aunque TrustProxies esté en '*'.
 *
 * Eso importaba especialmente en el consentimiento RGPD de la vinculación con
 * la gestoría: quedaba registrada la IP del proxy en vez de la de quien
 * autorizó el acceso a sus datos fiscales, lo que invalida el registro si
 * alguna vez hay una reclamación.
 *
 * Solo se confía en CF-Connecting-IP si la petición viene de un rango de
 * Cloudflare. Sin esa comprobación, cualquiera podría mandar la cabecera a
 * mano y falsear su IP justo en el dato que queremos que sea fiable.
 *
 * Los rangos son los publicados por Cloudflare en
 * https://www.cloudflare.com/ips/ y cambian muy de tarde en tarde; si algún
 * día dejan de cuadrar, la IP simplemente vuelve a ser la del proxy (que es
 * el comportamiento anterior), nunca se rompe la petición.
 */
class RealIpBehindCloudflare
{
    /**
     * Rangos IPv4 de Cloudflare.
     */
    private const CF_IPV4 = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
    ];

    /**
     * Rangos IPv6 de Cloudflare.
     */
    private const CF_IPV6 = [
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * Redes internas de Docker, que son las que ve la aplicación cuando la
     * petición llega a través de Caddy.
     */
    private const INTERNAS = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
    ];

    public function handle(Request $request, Closure $next)
    {
        $real = $request->headers->get('CF-Connecting-IP');

        if ($real && filter_var($real, FILTER_VALIDATE_IP) && $this->vieneDeCloudflare($request)) {
            // Se reescribe REMOTE_ADDR: así $request->ip() devuelve la buena
            // en toda la aplicación, sin tener que tocar cada sitio que la use.
            $request->server->set('REMOTE_ADDR', $real);
            $request->headers->set('X-Forwarded-For', $real);
        }

        return $next($request);
    }

    /**
     * ¿La petición viene realmente de Cloudflare?
     *
     * Se comprueba tanto la IP directa como la cadena de X-Forwarded-For,
     * porque entre Cloudflare y la aplicación está Caddy: la conexión directa
     * la hace el contenedor de Caddy desde una red interna de Docker.
     */
    private function vieneDeCloudflare(Request $request): bool
    {
        $candidatas = [$request->server->get('REMOTE_ADDR')];

        $forwarded = $request->headers->get('X-Forwarded-For');
        if ($forwarded) {
            foreach (explode(',', $forwarded) as $ip) {
                $candidatas[] = trim($ip);
            }
        }

        $rangos = array_merge(self::CF_IPV4, self::CF_IPV6, self::INTERNAS);

        foreach ($candidatas as $ip) {
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP) && IpUtils::checkIp($ip, $rangos)) {
                return true;
            }
        }

        return false;
    }
}
