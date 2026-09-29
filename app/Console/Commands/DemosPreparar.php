<?php

namespace App\Console\Commands;

use App\Services\Demo\EscenarioPruebas;
use App\Services\GestoriaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Onfactu v.1.14.6 — Deja la instancia de pruebas (demos) con un juego de
 * datos fijo, para probar cada versión sabiendo qué tiene que salir.
 *
 *   php artisan demos:preparar        ensayo: enseña lo que borraría
 *   php artisan demos:preparar --si   borra y crea los datos de prueba
 *
 * SEGURIDAD: solo actúa en la instancia demos (base onf_app_onf_demos). En
 * cualquier otra se niega, también con --si. No confundir con demo:reiniciar,
 * que es de la demo pública.
 *
 * Datos y cifras esperadas: docs/PRUEBAS_EN_DEMOS.md.
 */
class DemosPreparar extends Command
{
    protected $signature = 'demos:preparar {--si : Borrar los datos de negocio y crear los de prueba}';

    protected $description = 'Datos de prueba fijos en la instancia demos (solo en demos)';

    public function handle(): int
    {
        $sub = GestoriaService::subdominio();
        if ($sub !== 'demos') {
            $this->error("Esta instancia es {$sub}, no demos. No se toca nada.");

            return self::FAILURE;
        }

        $hoy = Carbon::today('Europe/Madrid');
        $escenario = new EscenarioPruebas($hoy);

        if (! $this->option('si')) {
            $this->info('Se borrarían estas filas (se conservan empresa, usuarios, ajustes, impuestos, formas de pago y gestoría):');
            $this->table(['Tabla', 'Filas'], $escenario->recuento());
            $this->warn('Ensayo: no se ha tocado nada. Para hacerlo: php artisan demos:preparar --si');

            return self::SUCCESS;
        }

        $escenario->preparar();
        $this->info('Datos de prueba creados con fecha '.$hoy->format('d/m/Y').'.');
        foreach ($escenario->despues() as $aviso) {
            $this->line('  '.$aviso);
        }

        $e = $escenario->esperado();
        $eur = fn (int $c) => number_format($c / 100, 2, ',', '.').' €';

        $this->newLine();
        $this->info('Lo que tiene que salir (solo facturas aprobadas; el borrador de 6.050 € no cuenta):');
        $this->table(['Dato', 'Valor'], [
            ['Facturas aprobadas (con 1 rectificativa)', $e['facturas']],
            ['Ventas (total con IVA)', $eur($e['ventas'])],
            ['Base imponible', $eur($e['base'])],
            ['IVA 21 %', $eur($e['iva'])],
            ['Pendiente de cobro', $eur($e['pendiente'])],
            ['Cobrado', $eur($e['cobrado'])],
            ['Gastos', $eur($e['gastos'])],
            ['Cobrado menos gastos', $eur($e['cobrado'] - $e['gastos'])],
        ]);

        $nombres = ['alfa' => 'Alfa Servicios S.L.', 'beta' => 'Beta Comercio S.L.', 'carmen' => 'Carmen Prueba Particular'];
        $this->table(['Cliente', 'Ventas'], collect($e['clientes'])
            ->map(fn ($v, $k) => [$nombres[$k] ?? $k, $eur($v)])->values()->all());

        $this->table(['Mes', 'Base', 'IVA', 'Total', 'Gastos'], collect($e['meses'])
            ->map(fn ($v, $m) => [$m, $eur($v['base'] ?? 0), $eur($v['iva'] ?? 0), $eur($v['total'] ?? 0), $eur($v['gastos'] ?? 0)])
            ->values()->all());

        return self::SUCCESS;
    }
}
