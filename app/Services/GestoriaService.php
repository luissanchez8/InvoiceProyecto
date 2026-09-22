<?php

namespace App\Services;

use App\Models\AppConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

        // Aviso a la gestoría de que tiene una solicitud esperando.
        self::avisarGestoria(
            $gestoria->email ?? null,
            'Nueva solicitud de vinculación',
            'Tienes una solicitud nueva',
            '<p><strong>' . e(self::nombreEmpresa()) . '</strong> quiere vincular su cuenta de Onfactu con vuestra gestoría.</p>'
            . '<p>Entra en el portal para aceptarla o denegarla:</p>'
            . '<p><a href="https://gestoria.onfactu.com/?view=solicitudes" '
            . 'style="display:inline-block;padding:10px 20px;background:#070322;color:#fff;'
            . 'text-decoration:none;border-radius:6px">Ver solicitudes</a></p>'
        );

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

            // Aviso a la gestoría de que tiene un mes nuevo.
            $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                      'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $periodo = ($meses[$month] ?? $month) . ' de ' . $year;

            self::avisarGestoria(
                $v->gestoria_email ?? null,
                'Mes recibido: ' . self::nombreEmpresa() . ' · ' . $periodo,
                'Has recibido ' . $periodo,
                '<p><strong>' . e(self::nombreEmpresa()) . '</strong> ha cerrado <strong>' . e($periodo) . '</strong>.</p>'
                . '<p>Ya tienes disponibles sus facturas y gastos de ese periodo en el portal.</p>'
                . '<p><a href="https://gestoria.onfactu.com/" '
                . 'style="display:inline-block;padding:10px 20px;background:#070322;color:#fff;'
                . 'text-decoration:none;border-radius:6px">Abrir el portal</a></p>'
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

    /**
     * Envía un aviso por email al contacto de la gestoría.
     *
     * De mejor esfuerzo: si no hay email o el envío falla, se registra y se
     * sigue. La acción que lo provoca (vincular, cerrar un mes) no debe
     * fallar porque el correo esté caído.
     */
    private static function avisarGestoria(?string $para, string $asunto, string $titulo, string $cuerpoHtml): void
    {
        if (! $para || ! filter_var($para, FILTER_VALIDATE_EMAIL)) {
            \Log::info('Gestoría sin email de contacto, aviso no enviado', ['asunto' => $asunto]);
            return;
        }

        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
            . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border-radius:8px;overflow:hidden">'
            . '<tr><td style="background:#070322;padding:20px 28px">'
            . '<span style="color:#fff;font-size:20px;font-weight:bold">onfactu</span>'
            . '<span style="color:#38d587;font-size:13px;font-weight:bold;margin-left:6px">GESTORÍA</span>'
            . '</td></tr>'
            . '<tr><td style="padding:28px">'
            . '<h1 style="margin:0 0 16px;font-size:20px;color:#070322">' . e($titulo) . '</h1>'
            . '<div style="font-size:15px;line-height:1.6;color:#334155">' . $cuerpoHtml . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 28px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8">'
            . 'Aviso automático de Onfactu. No respondas a este correo.'
            . '</td></tr></table></td></tr></table></body></html>';

        try {
            Mail::html($html, function ($m) use ($para, $asunto) {
                $m->to($para)->subject($asunto);
            });
        } catch (\Throwable $e) {
            \Log::warning('Aviso a la gestoría no enviado', ['para' => $para, 'error' => $e->getMessage()]);
        }
    }
}
