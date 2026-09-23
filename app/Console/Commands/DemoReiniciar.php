<?php

namespace App\Console\Commands;

use App\Services\Demo\AjustesDemo;
use App\Services\Demo\ComercialDemo;
use App\Services\Demo\ContextoDemo;
use App\Services\Demo\FacturasDemo;
use App\Services\Demo\GastosDemo;
use App\Services\Demo\LimpiezaDemo;
use App\Services\Demo\MaestrosDemo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Reinicia la demo pública (demo.onfactu.com): borra los datos de negocio,
 * restablece los ajustes y genera datos nuevos con fechas relativas a hoy.
 *
 * Se programa a las 00:00 (hora de Madrid) en routes/console.php.
 *
 * Sustituye a reset:app de InvoiceShelf, que hace migrate:fresh y dejaría una
 * base vacía sin la configuración de Onfactu que viene de plantilla.sql.
 *
 * SEGURIDAD: se niega a actuar si la instancia no está en modo demo
 * (APP_ENV=demo). La demo se llama demo-publica y la de pruebas "demos":
 * un despiste al escribir no debe poder borrar la instancia de pruebas.
 */
class DemoReiniciar extends Command
{
    protected $signature = 'demo:reiniciar';

    protected $description = 'Reinicia la demo pública con datos nuevos (solo con APP_ENV=demo)';

    public function handle(): int
    {
        if (config('app.env') !== 'demo') {
            $this->error('Esta instancia no está en modo demo (APP_ENV=' . config('app.env') . '). No se toca nada.');

            return self::FAILURE;
        }

        $hoy = Carbon::today('Europe/Madrid');

        // Azar reproducible: el mismo día genera siempre los mismos datos
        mt_srand(crc32($hoy->toDateString()));

        $inicio = microtime(true);

        // Todo o nada: si algo falla, la demo conserva los datos de ayer
        DB::transaction(function () use ($hoy) {
            (new LimpiezaDemo())->vaciar();
            (new AjustesDemo())->aplicar();

            $c = new ContextoDemo($hoy);
            $m = (new MaestrosDemo($c))->crear();
            $facturas = (new FacturasDemo($c, $m))->crear();
            (new ComercialDemo($c, $m, $facturas))->crear();
            (new GastosDemo($c, $m))->crear();
        });

        Artisan::call('cache:clear');

        $this->info(sprintf('Demo reiniciada en %.1f s con fecha %s', microtime(true) - $inicio, $hoy->toDateString()));
        $this->table(['Tabla', 'Filas'], collect([
            'customers', 'items', 'invoices', 'recurring_invoices', 'payments', 'estimates',
            'proforma_invoices', 'delivery_notes', 'expenses',
        ])->map(fn ($t) => [$t, DB::table($t)->count()])->all());

        return self::SUCCESS;
    }
}
