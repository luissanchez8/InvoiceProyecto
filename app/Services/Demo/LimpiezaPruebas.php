<?php

namespace App\Services\Demo;

use App\Models\ClosedMonth;
use App\Services\GestoriaService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Onfactu v.1.14.6 — Lo que borra demos:preparar antes de crear los datos de
 * prueba (separado de EscenarioPruebas en la v.1.15.0).
 *
 * Conserva la empresa, su dirección y su logo, los usuarios, los ajustes, los
 * tipos de impuesto, las formas de pago y la vinculación con la gestoría.
 */
class LimpiezaPruebas
{
    /** Tablas que se vacían enteras. Clientes y direcciones van aparte. */
    public const TABLAS = [
        'taxes', 'invoice_items', 'estimate_items', 'proforma_invoice_items', 'delivery_note_items',
        'payments', 'transactions', 'invoices', 'estimates', 'proforma_invoices', 'delivery_notes',
        'recurring_invoices', 'expenses', 'expense_categories', 'items', 'custom_field_values',
        'email_logs', 'closed_months', 'notifications', 'exchange_rate_logs',
    ];

    /**
     * Tablas de configuración que no se tocan nunca. Si alguna dependiera de
     * las que se vacían, se para sin borrar nada.
     */
    private const PROTEGIDAS = [
        'users', 'companies', 'company_settings', 'settings', 'app_config', 'tax_types',
        'payment_methods', 'units', 'currencies', 'countries', 'custom_fields', 'addresses',
        'customers', 'user_company', 'abilities', 'permissions', 'roles', 'assigned_roles',
    ];

    /** Lo que se borraría, sin tocar nada. */
    public static function recuento(): array
    {
        $filas = [];
        foreach (array_merge(self::TABLAS, self::dependientes()) as $t) {
            $filas[] = [$t, DB::table($t)->count()];
        }
        $filas[] = ['customers', DB::table('customers')->count()];
        $filas[] = ['addresses (de clientes)', DB::table('addresses')->whereNotNull('customer_id')->count()];
        $filas[] = ['media (sin logo ni avatares)', self::media()->count()];
        $filas[] = ['tax_types "Impuesto N" (basura)', self::impuestosBasura()->count()];

        return $filas;
    }

    /**
     * Tablas que no están en la lista pero dependen de alguna que sí (por
     * ejemplo, las líneas de IVA de los gastos o los registros de VeriFactu
     * de las facturas). Se vacían con ellas.
     */
    public static function dependientes(): array
    {
        $todas = self::TABLAS;
        do {
            $nuevas = collect(DB::select(
                "SELECT DISTINCT hija.relname AS tabla
                   FROM pg_constraint c
                   JOIN pg_class hija  ON hija.oid  = c.conrelid
                   JOIN pg_class madre ON madre.oid = c.confrelid
                  WHERE c.contype = 'f' AND madre.relname = ANY(?::text[])",
                ['{'.implode(',', $todas).'}']
            ))->pluck('tabla')->diff($todas)->values()->all();
            $todas = array_merge($todas, $nuevas);
        } while ($nuevas);

        $extra = array_values(array_diff($todas, self::TABLAS));
        $prohibidas = array_intersect($extra, self::PROTEGIDAS);
        if ($prohibidas) {
            throw new RuntimeException('Estas tablas de configuración dependen de las que se vacían: '
                .implode(', ', $prohibidas).'. No se ha borrado nada.');
        }

        return $extra;
    }

    /** Dentro de la transacción de EscenarioPruebas. */
    public static function vaciar(): void
    {
        $tablas = array_merge(self::TABLAS, self::dependientes());

        // Sin CASCADE: si otra tabla dependiera de estas, PostgreSQL se niega
        // en vez de vaciarla a escondidas.
        DB::statement('TRUNCATE TABLE '.implode(', ', array_map(fn ($t) => '"'.$t.'"', $tablas)).' RESTART IDENTITY');

        // La dirección de la empresa no tiene cliente: se queda
        DB::table('addresses')->whereNotNull('customer_id')->delete();
        DB::table('customers')->delete();
        self::media()->delete();

        ClosedMonth::forgetCache();
    }

    /**
     * Borra los cierres antiguos de demos en la central de gestorías. Devuelve
     * un aviso si no pudo y quedan meses que no se van a sustituir: el usuario
     * con el que la instancia se conecta a la central no puede borrar.
     */
    public static function limpiarCentral(): ?string
    {
        $central = fn () => DB::connection('gestorias')->table('gestoria_cierres')
            ->where('subdominio', GestoriaService::subdominio());
        try {
            $central()->delete();

            return null;
        } catch (\Throwable $e) {
            try {
                $nuevos = ClosedMonth::periodsFor(1);
                $quedan = $central()->get(['year', 'month'])
                    ->reject(fn ($r) => in_array(sprintf('%04d-%02d', $r->year, $r->month), $nuevos, true))
                    ->count();
            } catch (\Throwable $e2) {
                $quedan = 1;
            }

            return $quedan ? 'No se pudieron borrar los cierres antiguos de demos en la central (permisos): '
                .'pueden seguir viéndose en el portal junto a los nuevos.' : null;
        }
    }

    /** Fuera de la transacción: si falla, no deshace lo demás. */
    public static function borrarImpuestosBasura(): ?string
    {
        try {
            $n = self::impuestosBasura()->delete();

            return $n ? "Borrados {$n} tipos de impuesto de prueba (\"Impuesto N\")." : null;
        } catch (\Throwable $e) {
            return 'No se pudieron borrar los tipos de impuesto "Impuesto N": '.$e->getMessage();
        }
    }

    private static function media()
    {
        return DB::table('media')->whereNotIn('model_type', ['App\\Models\\Company', 'App\\Models\\User']);
    }

    private static function impuestosBasura()
    {
        return DB::table('tax_types')->where('name', '~', '^Impuesto [0-9]+$')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('taxes')->whereColumn('taxes.tax_type_id', 'tax_types.id'));
    }
}
