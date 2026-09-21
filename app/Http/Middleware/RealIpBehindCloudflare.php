<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Onfactu — IP real del visitante detrás de Cloudflare.
 *
 * Todas las instancias van detrás de Cloudflare, que pone la IP del visitante
 * en la cabecera CF-Connecting-IP. Sin este middleware, $request->ip()
 * devolvía la de Cloudflare, y así se guardaba en el consentimiento RGPD.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * IMPORTANTE: debe ejecutarse ANTES de TrustProxies, y NO debe tocar
 * REMOTE_ADDR.
 *
 * La primera versión reescribía REMOTE_ADDR y se ejecutaba después de
 * TrustProxies. Eso rompía el login en producción: TrustProxies con '*'
 * confía en el REMOTE_ADDR que tiene la petición en ese momento (el de
 * Caddy). Al cambiarlo después, la petición dejaba de "venir de un proxy de
 * confianza", Laravel ignoraba X-Forwarded-Proto, creía que la conexión era
 * http, y tras el login redirigía a http://.../dashboard. El navegador lo
 * bloqueaba por contenido mixto y el usuario tenía que recargar.
 *
 * La forma correcta es dejar REMOTE_ADDR intacto y poner la IP real en
 * X-Forwarded-For. TrustProxies, confiando en Caddy, resuelve entonces la IP
 * del cliente a partir de esa cabecera por su cuenta, y sigue respetando
 * X-Forwarded-Proto porque la petición sigue viniendo de un proxy de confianza.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Solo se hace caso a CF-Connecting-IP si la petición llega a través de
 * Cloudflare o de la red interna de Docker. Sin esa comprobación, cualquiera
 * podría mandar la cabecera a mano y falsear su IP justo en el dato que debe
 * ser fiable.
 */
class RealIpBehindCloudflare
{
    private const CF_IPV4 = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    ];

    private const CF_IPV6 = [
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * Redes internas de Docker: la conexión directa la hace el contenedor de
     * Caddy desde una de estas.
     */
    private const INTERNAS = [
        '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8',
    ];

    public function handle(Request $request, Closure $next)
    {
        $real = $request->headers->get('CF-Connecting-IP');

        if ($real && filter_var($real, FILTER_VALIDATE_IP) && $this->vieneDeProxyPropio($request)) {
            // Solo la cabecera. REMOTE_ADDR se queda como está: es la
            // referencia que TrustProxies usa para decidir en quién confía.
            $request->headers->set('X-Forwarded-For', $real);
            $request->server->set('HTTP_X_FORWARDED_FOR', $real);
        }

        return $next($request);
    }

    /**
     * ¿La petición llega a través de nuestra cadena de proxies?
     *
     * Se mira la conexión directa (REMOTE_ADDR, que es Caddy desde la red de
     * Docker) y la cadena de X-Forwarded-For original, donde aparece
     * Cloudflare.
     */
    private function vieneDeProxyPropio(Request $request): bool
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
