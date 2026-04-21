<?php

namespace App\Console\Commands;

use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Onfactu — Reparación de sequence_number.
 *
 * Recorre facturas, presupuestos, proformas y albaranes en estados emitidos
 * (APPROVED/SIGNED para facturas, SENT/VIEWED/ACCEPTED/DELIVERED para los
 * demás) que tengan sequence_number NULL, intenta parsear la parte numérica
 * de su identificador (ej. "INV-000042" → 42) y le asigna ese valor como
 * sequence_number.
 *
 * Esto sirve para reparar datos históricos o bugs que hayan dejado registros
 * aprobados sin sequence_number asignado. Sin sequence_number, la
 * numeración automática se "atasca" porque MAX(sequence_number) no refleja
 * la realidad.
 *
 * Uso:
 *   php artisan invoices:repair-sequence
 *   php artisan invoices:repair-sequence --dry-run         (solo mostrar)
 *   php artisan invoices:repair-sequence --company=1       (una empresa)
 *   php artisan invoices:repair-sequence --type=invoice    (un solo tipo)
 */
class RepairSequenceNumbers extends Command
{
    protected $signature = 'invoices:repair-sequence
                            {--dry-run : Solo mostrar qué se repararía, sin modificar nada}
                            {--company= : Reparar solo una empresa por ID}
                            {--type=all : Tipo de documento: invoice, estimate, proforma, delivery, all}';

    protected $description = 'Repara sequence_number NULL en documentos emitidos parseando su número';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $companyId = $this->option('company');
        $type = $this->option('type');

        if ($dryRun) {
            $this->warn('Modo DRY-RUN: no se aplicará ningún cambio.');
        }

        $totalFixed = 0;

        if ($type === 'all' || $type === 'invoice') {
            $totalFixed += $this->repair(
                Invoice::class,
                'invoice_number',
                ['APPROVED'], // estado(s) emitidos para facturas
                $companyId,
                $dryRun,
                'Facturas'
            );
        }

        if ($type === 'all' || $type === 'estimate') {
            $totalFixed += $this->repair(
                Estimate::class,
                'estimate_number',
                ['SENT', 'VIEWED', 'ACCEPTED'],
                $companyId,
                $dryRun,
                'Presupuestos'
            );
        }

        if ($type === 'all' || $type === 'proforma') {
            $totalFixed += $this->repair(
                ProformaInvoice::class,
                'proforma_invoice_number',
                ['SENT', 'VIEWED', 'ACCEPTED'],
                $companyId,
                $dryRun,
                'Proformas'
            );
        }

        if ($type === 'all' || $type === 'delivery') {
            $totalFixed += $this->repair(
                DeliveryNote::class,
                'delivery_note_number',
                ['SENT', 'DELIVERED'],
                $companyId,
                $dryRun,
                'Albaranes'
            );
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY-RUN: se habrían reparado {$totalFixed} registros.");
        } else {
            $this->info("Reparados {$totalFixed} registros.");
        }

        return self::SUCCESS;
    }

    /**
     * Repara un tipo de documento.
     *
     * @param  class-string  $modelClass
     * @param  string  $numberField
     * @param  array<string>  $emittedStatuses
     */
    protected function repair(
        string $modelClass,
        string $numberField,
        array $emittedStatuses,
        ?string $companyId,
        bool $dryRun,
        string $label,
    ): int {
        $this->info("--- {$label} ---");

        $query = $modelClass::whereNull('sequence_number')
            ->whereNotNull($numberField)
            ->whereIn('status', $emittedStatuses);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $records = $query->orderBy('id')->get();

        if ($records->isEmpty()) {
            $this->line('  Sin registros que reparar.');

            return 0;
        }

        $fixed = 0;

        foreach ($records as $record) {
            $parsed = $this->parseSequenceFromNumber($record->{$numberField});

            if ($parsed === null) {
                $this->warn("  [id={$record->id}] No se pudo parsear '{$record->{$numberField}}' — se omite.");
                continue;
            }

            // Verificar que ese sequence_number no está ya en uso en otro
            // registro de la misma empresa (evitar duplicados de sequence).
            $collision = $modelClass::where('company_id', $record->company_id)
                ->where('sequence_number', $parsed)
                ->where('id', '<>', $record->id)
                ->exists();

            if ($collision) {
                $this->warn("  [id={$record->id}] sequence_number {$parsed} ya está en uso por otro registro — se omite.");
                continue;
            }

            $this->line("  [id={$record->id}] {$record->{$numberField}} → sequence_number = {$parsed}");

            if (! $dryRun) {
                $record->sequence_number = $parsed;
                $record->save();
            }

            $fixed++;
        }

        $this->line("  Total: {$fixed} registro(s) " . ($dryRun ? 'a reparar' : 'reparados'));

        return $fixed;
    }

    /**
     * Extrae la parte numérica del final del identificador.
     *
     * "INV-000030" → 30
     * "F-2024-0015" → 15
     * "ABC123" → 123
     * "sin-numeros" → null
     */
    protected function parseSequenceFromNumber(string $number): ?int
    {
        // Captura la última secuencia de dígitos del string.
        if (preg_match('/(\d+)(?!.*\d)/', $number, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
