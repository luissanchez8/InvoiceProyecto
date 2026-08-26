<?php

namespace App\Services;

use App\Models\AppConfig;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu — Vinculación de la instancia con una gestoría.
 *
 * Toda la comunicación con la BD central `onfactu_gestorias` pasa por aquí,
 * usando la conexión 'gestorias' (usuario con permisos mínimos: solo puede
 * tocar su propia vinculación y sus cierres, nada más).
 *
 * Reglas:
 *   · El toggle GESTORIA_ACTIVA lo controla el propio cliente, y viene
 *     desactivado por defecto.
 *   · Un cliente solo puede tener UNA gestoría vinculada a la vez. Lo
 *     garantiza un índice único parcial en la BD central, no solo el código.
 *   · Para desactivar el toggle hay que desvincular antes.
 *   · Al vincular queda registrado el consentimiento (RGPD): quién, cuándo y
 *     desde qué IP autorizó el acceso a sus datos fiscales.
 */
class GestoriaService
{
    /** Nombre de la conexión definida en config/database.php */
    private const CONN = 'gestorias';

    /** Subdominio de esta instancia, deducido de la BD (onf_app_onf_<sub>). */
    public static function subdominio(): string
    {
        $db = DB::connection()->getDatabaseName();          // onf_app_onf_dagrotec

        return str_replace('_', '-', preg_replace('/^onf_app_onf_/', '', $db));
    }

    /** ¿El cliente ha activado la sección de gestoría? */
    public static function activa(): bool
    {
        return (string) app_cfg('GESTORIA_ACTIVA', '0') === '1';
    }

    public static function setActiva(bool $valor): void
    {
        AppConfig::updateOrCreate(
            ['key' => 'GESTORIA_ACTIVA'],
            ['value' => $valor ? '1' : '0']
        );
    }

    /**
     * Vinculación actual (pendiente o aceptada), o null si no hay ninguna.
     */
    public static function vinculacion(): ?object
    {
        return DB::connection(self::CONN)
            ->table('gestoria_cuentas as gc')
            ->join('gestorias as g', 'g.id', '=', 'gc.gestoria_id')
            ->where('gc.subdominio', self::subdominio())
            ->whereIn('gc.estado', ['pendiente', 'aceptada'])
            ->select([
                'gc.id', 'gc.estado', 'gc.solicitada_at', 'gc.resuelta_at',
                'gc.consentimiento_at',
                'g.nombre as gestoria_nombre', 'g.nif as gestoria_nif',
                'g.email as gestoria_email', 'g.telefono as gestoria_telefono',
            ])
            ->first();
    }

    /**
     * Estado completo para pintar la pantalla.
     */
    public static function estado(): array
    {
        $v = self::vinculacion();

        return [
            'activa'      => self::activa(),
            'subdominio'  => self::subdominio(),
            'vinculada'   => $v && $v->estado === 'aceptada',
            'pendiente'   => $v && $v->estado === 'pendiente',
            'gestoria'    => $v ? [
                'nombre'   => $v->gestoria_nombre,
                'nif'      => $v->gestoria_nif,
                'email'    => $v->gestoria_email,
                'telefono' => $v->gestoria_telefono,
                'desde'    => $v->resuelta_at,
                'solicitada' => $v->solicitada_at,
            ] : null,
        ];
    }

    /**
     * Solicita vinculación con el código de una gestoría.
     * Devuelve ['ok' => bool, 'message' => string].
     */
    public static function solicitar(string $codigo, string $ip, string $usuario): array
    {
        $codigo = strtoupper(trim($codigo));

        if (! self::activa()) {
            return ['ok' => false, 'message' => 'Primero tienes que activar la gestoría en los ajustes.'];
        }

        $gestoria = DB::connection(self::CONN)
            ->table('gestorias')
            ->where('codigo', $codigo)
            ->where('activa', true)
            ->first();

        if (! $gestoria) {
            return ['ok' => false, 'message' => 'Ese código de gestoría no existe. Revísalo con tu gestoría.'];
        }

        $actual = self::vinculacion();
        if ($actual) {
            return $actual->estado === 'aceptada'
                ? ['ok' => false, 'message' => 'Ya tienes una gestoría vinculada. Desvincúlala antes de añadir otra.']
                : ['ok' => false, 'message' => 'Ya has enviado una solicitud y está pendiente de aprobación.'];
        }

        try {
            DB::connection(self::CONN)->table('gestoria_cuentas')->insert([
                'gestoria_id'    => $gestoria->id,
                'subdominio'     => self::subdominio(),
                'empresa_nombre' => self::nombreEmpresa(),
                'empresa_nif'    => self::nifEmpresa(),
                'estado'         => 'pendiente',
                'solicitada_at'  => now(),
                // Consentimiento del cliente (RGPD)
                'consentimiento_at'      => now(),
                'consentimiento_ip'      => $ip,
                'consentimiento_usuario' => $usuario,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // El índice único parcial puede rechazar duplicados en carrera
            return ['ok' => false, 'message' => 'No se pudo enviar la solicitud. Inténtalo de nuevo.'];
        }

        self::log('solicitud_vinculacion', ['gestoria' => $gestoria->nombre, 'codigo' => $codigo], $ip);

        return [
            'ok' => true,
            'message' => 'Solicitud enviada a '.$gestoria->nombre.'. Te avisaremos cuando la acepten.',
        ];
    }

    /**
     * El cliente corta el vínculo con su gestoría.
     */
    public static function desvincular(string $ip, string $usuario, ?string $motivo = null): array
    {
        $v = self::vinculacion();

        if (! $v) {
            return ['ok' => false, 'message' => 'No tienes ninguna gestoría vinculada.'];
        }

        DB::connection(self::CONN)
            ->table('gestoria_cuentas')
            ->where('id', $v->id)
            ->update([
                'estado'            => 'desvinculada',
                'desvinculada_at'   => now(),
                'desvinculada_por'  => 'cliente',
                'desvinculada_nota' => $motivo,
                'updated_at'        => now(),
            ]);

        self::log('desvinculacion', ['gestoria' => $v->gestoria_nombre, 'por' => 'cliente'], $ip);

        return ['ok' => true, 'message' => 'Se ha desvinculado la gestoría correctamente.'];
    }

    /**
     * Registra un cierre de mes en la central para que el portal lo vea.
     * Devuelve true si se registró; false si falló (no bloquea el cierre).
     */
    public static function registrarCierre(int $year, int $month, array $totales, $cerradoAt): bool
    {
        $v = self::vinculacion();
        if (! $v || $v->estado !== 'aceptada') {
            return false;                     // sin gestoría no hay a quién enviar
        }

        try {
            DB::connection(self::CONN)->table('gestoria_cierres')->updateOrInsert(
                ['subdominio' => self::subdominio(), 'year' => $year, 'month' => $month],
                [
                    'totales'     => json_encode($totales),
                    'cerrado_at'  => $cerradoAt,
                    'recibido_at' => now(),
                ]
            );

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────

    private static function nombreEmpresa(): ?string
    {
        try {
            return DB::table('companies')->orderBy('id')->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function nifEmpresa(): ?string
    {
        try {
            $c = DB::table('companies')->orderBy('id')->first(['vat_id', 'tax_id']);

            return $c ? ($c->vat_id ?: $c->tax_id) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function log(string $accion, array $detalle, ?string $ip = null): void
    {
        try {
            DB::connection(self::CONN)->table('gestoria_log')->insert([
                'accion'     => $accion,
                'subdominio' => self::subdominio(),
                'detalle'    => json_encode($detalle),
                'ip'         => $ip,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // el log nunca debe romper la operación
        }
    }
}
