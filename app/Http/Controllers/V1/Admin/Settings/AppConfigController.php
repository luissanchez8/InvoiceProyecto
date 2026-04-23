<?php
namespace App\Http\Controllers\V1\Admin\Settings;
use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AppConfigController extends Controller
{
    /**
     * Claves de app_config que DEBEN existir siempre (con valor por defecto).
     * Si una instancia no las tiene, se crean automáticamente al acceder al panel.
     *
     * Esto evita que al añadir un toggle nuevo (como OPCION_VERIFACTU en v1.8.5)
     * haya que hacer migración manual en cada instancia existente: con solo
     * abrir el panel de Asistencia por primera vez tras el deploy, el toggle
     * aparece con su valor por defecto.
     */
    private const DEFAULT_KEYS = [
        'OPCION_MENU_FACTURAS'       => '1',
        'OPCION_MENU_PRESUPUESTOS'   => '1',
        'OPCION_MENU_PROFORMAS'      => '1',
        'OPCION_MENU_ALBARANES'      => '1',
        'OPCION_MENU_FRA_RECURRENTE' => '1',
        'OPCION_MENU_PAGOS'          => '1',
        'OPCION_MENU_GASTOS'         => '1',
        'OPCION_VERIFACTU'           => '0', // VeriFactu desactivado por defecto
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'asistencia') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Asegurar que todas las claves esperadas existen en BD (auto-seed).
        $existing = AppConfig::whereIn('key', array_keys(self::DEFAULT_KEYS))
            ->pluck('key')
            ->toArray();

        foreach (self::DEFAULT_KEYS as $key => $defaultValue) {
            if (!in_array($key, $existing, true)) {
                AppConfig::create(['key' => $key, 'value' => $defaultValue]);
            }
        }

        $configs = AppConfig::all()->map(function ($item) {
            return ['id' => $item->id, 'key' => $item->key, 'value' => $item->value];
        });
        return response()->json(['data' => $configs]);
    }
    public function update(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'asistencia') {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*.key' => 'required|string',
            'configs.*.value' => 'required|string',
        ]);
        foreach ($validated['configs'] as $config) {
            AppConfig::updateOrCreate(['key' => $config['key']], ['value' => $config['value']]);
        }
        return response()->json(['success' => true, 'message' => 'Configuracion actualizada']);
    }

    /**
     * Consulta la BD de Stripe para obtener el plan del usuario admin de esta instancia.
     * Busca por email del admin (primer usuario, role super admin) en users_onfactu_stripe.
     */
    public function planFromStripe(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'asistencia') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Obtener email del admin (primer usuario con role super admin)
        $admin = DB::table('users')
            ->where('role', 'super admin')
            ->orderBy('id')
            ->first();

        if (!$admin) {
            return response()->json(['ok' => false, 'error' => 'No se encontró usuario admin'], 404);
        }

        try {
            $stripeUser = DB::connection('stripe')
                ->table('users')
                ->whereRaw('LOWER(email) = ?', [strtolower($admin->email)])
                ->first();

            if (!$stripeUser) {
                return response()->json([
                    'ok' => false,
                    'error' => 'No se encontró el email en la BD de Stripe',
                    'admin_email' => $admin->email,
                ]);
            }

            return response()->json([
                'ok' => true,
                'admin_email' => $admin->email,
                'plan_id' => $stripeUser->plan_id ?? null,
                'plan_interval' => $stripeUser->plan_interval ?? null,
                'plan_status' => $stripeUser->plan_status ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Error al conectar con la BD de Stripe: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Devuelve info básica del plan para mostrar en el sidebar.
     * Accesible para cualquier usuario logueado.
     */
    public function myPlan(Request $request)
    {
        // Obtener email del admin de esta instancia
        $admin = DB::table('users')
            ->where('role', 'super admin')
            ->orderBy('id')
            ->first();

        if (!$admin) {
            return response()->json(['ok' => false, 'plan_name' => 'Sin plan']);
        }

        try {
            $stripeUser = DB::connection('stripe')
                ->table('users')
                ->whereRaw('LOWER(email) = ?', [strtolower($admin->email)])
                ->first();

            if (!$stripeUser || !$stripeUser->plan_id) {
                return response()->json([
                    'ok' => true,
                    'plan_name' => 'Sin plan',
                    'portal_url' => null,
                ]);
            }

            // Mapear plan_id a nombre legible
            $planNames = [
                'pro' => 'Onfactu Pro',
                'plus' => 'Onfactu Plus',
                'advanced' => 'Onfactu Advanced',
                'price_1TE65XIDvd7prBStNslGwhBJ' => 'Onfactu Pro',
                'price_1TE66tIDvd7prBStdBLIqspz' => 'Onfactu Pro',
            ];
            $planName = $planNames[$stripeUser->plan_id] ?? ('Onfactu ' . ucfirst($stripeUser->plan_id ?? ''));

            // URL del portal de Stripe
            $portalUrl = 'https://pagos.onfactu.com/api/stripe/portal.php?email=' . urlencode($admin->email);

            return response()->json([
                'ok' => true,
                'plan_name' => $planName,
                'plan_status' => $stripeUser->plan_status ?? null,
                'portal_url' => $portalUrl,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'plan_name' => 'Sin plan',
                'portal_url' => null,
            ]);
        }
    }
}
