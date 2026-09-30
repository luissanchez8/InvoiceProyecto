<?php

namespace App\Services\Facturacion;

use App\Models\DeliveryNote;
use App\Models\Estimate;
use App\Models\ProformaInvoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Onfactu v.1.17.0 — Los tres documentos comerciales que se facturan
 * (presupuestos, proformas y albaranes) descritos en un solo sitio.
 *
 * El resto de la fase 3 (conversión, anticipos, estado de facturación y
 * bloqueo) trabaja con el "tipo" ('estimate', 'proforma', 'delivery_note') y
 * saca de aquí el modelo, la tabla y las columnas de cada uno, en vez de
 * repetir un if por documento.
 */
class Documentos
{
    public const TIPOS = [
        'estimate' => [
            'modelo'   => Estimate::class,
            'tabla'    => 'estimates',
            'segmento' => 'estimates',
            'numero'   => 'estimate_number',
            'fecha'    => 'estimate_date',
            'fk_item'  => 'estimate_id',
            'fk_tax_item' => 'estimate_item_id',
            'nombre'   => 'Presupuesto',
            'plural'   => 'presupuestos',
            'el'       => 'el presupuesto',
            'o'        => 'o',
        ],
        'proforma' => [
            'modelo'   => ProformaInvoice::class,
            'tabla'    => 'proforma_invoices',
            'segmento' => 'proforma-invoices',
            'numero'   => 'proforma_invoice_number',
            'fecha'    => 'proforma_invoice_date',
            'fk_item'  => 'proforma_invoice_id',
            'fk_tax_item' => 'proforma_invoice_item_id',
            'nombre'   => 'Proforma',
            'plural'   => 'proformas',
            'el'       => 'la proforma',
            'o'        => 'a',
        ],
        'delivery_note' => [
            'modelo'   => DeliveryNote::class,
            'tabla'    => 'delivery_notes',
            'segmento' => 'delivery-notes',
            'numero'   => 'delivery_note_number',
            'fecha'    => 'delivery_note_date',
            'fk_item'  => 'delivery_note_id',
            'fk_tax_item' => 'delivery_note_item_id',
            'nombre'   => 'Albarán',
            'plural'   => 'albaranes',
            'el'       => 'el albarán',
            'o'        => 'o',
        ],
    ];

    /** Tipos de los que se puede facturar un anticipo. */
    public const CON_ANTICIPO = ['estimate', 'proforma'];

    public static function cfg(string|Model $tipo): array
    {
        $tipo = $tipo instanceof Model ? self::tipoDe($tipo) : $tipo;
        if (! isset(self::TIPOS[$tipo])) {
            throw new \InvalidArgumentException('Tipo de documento desconocido: '.$tipo);
        }

        return self::TIPOS[$tipo];
    }

    public static function tipoDe(Model $doc): string
    {
        foreach (self::TIPOS as $tipo => $cfg) {
            if ($doc instanceof $cfg['modelo']) {
                return $tipo;
            }
        }
        throw new \InvalidArgumentException('No es un documento comercial: '.get_class($doc));
    }

    public static function tipoPorSegmento(string $segmento): ?string
    {
        foreach (self::TIPOS as $tipo => $cfg) {
            if ($cfg['segmento'] === $segmento) {
                return $tipo;
            }
        }

        return null;
    }

    public static function buscar(string $tipo, int $id, ?int $empresa = null): ?Model
    {
        $modelo = self::cfg($tipo)['modelo'];

        return $modelo::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa))
            ->find($id);
    }

    public static function numero(Model $doc): string
    {
        return (string) ($doc->{self::cfg($doc)['numero']} ?: '#'.$doc->id);
    }

    /** "Presupuesto PRE-000004" */
    public static function titulo(Model $doc): string
    {
        return self::cfg($doc)['nombre'].' '.self::numero($doc);
    }

    /** "El presupuesto PRE-000004 ya está facturado." */
    public static function yaFacturado(Model $doc): string
    {
        $cfg = self::cfg($doc);

        return ucfirst($cfg['el']).' '.self::numero($doc).' ya está facturad'.$cfg['o'].'.';
    }
}
