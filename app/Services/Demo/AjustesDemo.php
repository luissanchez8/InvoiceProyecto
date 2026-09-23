<?php

namespace App\Services\Demo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deja la empresa, el usuario y los ajustes de la demo como deben estar.
 *
 * Se aplica en cada reinicio, así que si un visitante cambió el nombre de la
 * empresa o algún ajuste, a medianoche vuelve a su sitio.
 */
class AjustesDemo
{
    /** El usuario con el que se entra en la demo, desde el botón de la portada. */
    public const EMAIL_USUARIO = 'demo@onfactu.com';

    public function aplicar(): void
    {
        $e = CatalogoDemo::empresa();
        $ahora = now();

        DB::table('companies')->where('id', 1)->update([
            'name' => $e['nombre'], 'tax_id' => $e['nif'], 'vat_id' => 'ES' . $e['nif'],
            'contact_email' => $e['email'], 'contact_name' => $e['titular'], 'website' => $e['web'],
            'updated_at' => $ahora,
        ]);

        // Dirección de la empresa: sin cliente asociado, así la encuentra Company::address()
        DB::table('addresses')->where('company_id', 1)->whereNull('customer_id')->delete();
        DB::table('addresses')->insert([
            'name' => $e['nombre'], 'address_street_1' => $e['direccion'], 'city' => $e['ciudad'],
            'state' => $e['provincia'], 'zip' => $e['cp'], 'phone' => $e['telefono'],
            'country_id' => DB::table('countries')->where('code', 'ES')->value('id'),
            'company_id' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);

        // Contraseñas aleatorias en cada reinicio: a la demo se entra por el
        // botón de la portada, y nadie debe poder entrar con el formulario.
        $admin = DB::table('users')->where('role', 'super admin')->orderBy('id')->first();
        DB::table('users')->where('id', $admin->id)->update([
            'name' => $e['titular'], 'email' => self::EMAIL_USUARIO,
            'password' => Hash::make(Str::random(48)), 'updated_at' => $ahora,
        ]);
        DB::table('users')->where('id', '!=', $admin->id)->update(['password' => Hash::make(Str::random(48))]);

        foreach ($this->appConfig($e) as $clave => $valor) {
            DB::table('app_config')->updateOrInsert(['key' => $clave], ['value' => $valor, 'updated_at' => $ahora]);
        }

        $moneda = DB::table('currencies')->where('code', 'EUR')->value('id');
        foreach ($this->ajustesEmpresa((string) $moneda) as $clave => $valor) {
            DB::table('company_settings')->updateOrInsert(
                ['option' => $clave, 'company_id' => 1],
                ['value' => $valor, 'updated_at' => $ahora]
            );
        }
    }

    private function appConfig(array $e): array
    {
        return [
            'NOMBRE_EMPRESA' => $e['nombre'],
            'OPCION_MENU_ALBARANES' => '1', 'OPCION_MENU_FACTURAS' => '1', 'OPCION_MENU_FRA_RECURRENTE' => '1',
            'OPCION_MENU_GASTOS' => '1', 'OPCION_MENU_PAGOS' => '1', 'OPCION_MENU_PRESUPUESTOS' => '1',
            'OPCION_MENU_PROFORMAS' => '1',
            // Sin Stripe: plan activo y sin periodo de prueba
            'STRIPE_PLAN_STATUS' => 'active', 'STRIPE_TRIAL_ENDS_AT' => '', 'STRIPE_TRIAL_PLAN' => '',
            'STRIPE_TRIAL_INTERVAL' => '',
            // La gestoría de la demo se conecta en una segunda fase
            'GESTORIA_ACTIVA' => '0',
        ];
    }

    /** Ajustes que un visitante podría romper y que se restablecen cada noche. */
    private function ajustesEmpresa(string $moneda): array
    {
        return [
            'language' => 'es', 'currency' => $moneda, 'time_zone' => 'Europe/Madrid', 'fiscal_year' => '1-12',
            'tax_per_item' => 'NO', 'discount_per_item' => 'NO',
            'invoice_set_due_date_automatically' => 'YES', 'invoice_due_date_days' => '30',
            'estimate_set_expiry_date_automatically' => 'YES', 'estimate_expiry_date_days' => '15',
            'retrospective_edits' => 'disable_on_invoice_paid',
            'notification_email' => self::EMAIL_USUARIO,
            'notify_invoice_viewed' => 'NO', 'notify_estimate_viewed' => 'NO',
            'verifactu_enabled' => 'NO',
            'invoice_number_format' => '{{SERIES:FAC}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'estimate_number_format' => '{{SERIES:PRE}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'payment_number_format' => '{{SERIES:PAG}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'proformainvoice_number_format' => '{{SERIES:PRO}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'deliverynote_number_format' => '{{SERIES:ALB}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            'rectificative_number_format' => '{{SERIES:REC}}{{DELIMITER:-}}{{SEQUENCE:6}}',
        ];
    }
}
