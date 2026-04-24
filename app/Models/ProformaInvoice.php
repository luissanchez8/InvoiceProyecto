<?php

/**
 * Modelo ProformaInvoice — Factura Proforma
 *
 * Representa una factura proforma (documento previo a la factura real).
 * Tiene la misma estructura que una factura pero NO gestiona pagos.
 * Puede convertirse en una factura real mediante convertToInvoice().
 *
 * Estados posibles: DRAFT → SENT → VIEWED → ACCEPTED / REJECTED
 *
 * Relaciones principales:
 *   - items()    → ProformaInvoiceItem (líneas del documento)
 *   - taxes()    → Tax (impuestos aplicados)
 *   - customer() → Customer (cliente destinatario)
 *   - company()  → Company (empresa emisora)
 *   - creator()  → User (usuario que creó el documento)
 *   - currency() → Currency (moneda del documento)
 *   - convertedInvoice() → Invoice (factura resultante de la conversión)
 */

namespace App\Models;

use App;
use App\Facades\PDF;
use App\Services\SerialNumberFormatter;
use App\Space\PdfTemplateUtils;
use App\Traits\GeneratesPdfTrait;
use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Vinkla\Hashids\Facades\Hashids;

class ProformaInvoice extends Model implements HasMedia
{
    use GeneratesPdfTrait;
    use HasCustomFieldsTrait;
    use HasFactory;
    use InteractsWithMedia;

    // --- Constantes de estado ---
    // Una proforma NO tiene estados de pago (no se cobra directamente)
    public const STATUS_DRAFT = 'DRAFT';       // Borrador inicial
    public const STATUS_SENT = 'SENT';         // Enviada al cliente
    public const STATUS_VIEWED = 'VIEWED';     // Vista por el cliente
    public const STATUS_ACCEPTED = 'ACCEPTED'; // Aceptada por el cliente
    public const STATUS_REJECTED = 'REJECTED'; // Rechazada por el cliente

    protected $table = 'proforma_invoices';

    protected $dates = [
        'created_at',
        'updated_at',
        'proforma_invoice_date',
        'expiry_date',
    ];

    protected $guarded = ['id'];

    protected $appends = [
        'formattedCreatedAt',
        'formattedProformaInvoiceDate',
        'formattedExpiryDate',
        'proformaInvoicePdfUrl',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'tax' => 'integer',
            'sub_total' => 'integer',
            'discount' => 'float',
            'discount_val' => 'integer',
            'exchange_rate' => 'float',
        ];
    }

    // =====================================================================
    // RELACIONES
    // =====================================================================

    /** Líneas/ítems de la factura proforma */
    public function items(): HasMany
    {
        return $this->hasMany(ProformaInvoiceItem::class);
    }

    /** Impuestos aplicados al documento */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    /** Registro de emails enviados */
    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'mailable');
    }

    /** Moneda del documento */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** Empresa emisora */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Cliente destinatario */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Usuario creador */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** Factura real resultante de la conversión (nullable) */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    // =====================================================================
    // ATRIBUTOS COMPUTADOS (Accessors / Appends)
    // =====================================================================

    /** URL pública para descargar el PDF */
    public function getProformaInvoicePdfUrlAttribute()
    {
        return url('/proforma-invoices/pdf/' . $this->unique_hash);
    }

    /** Fecha de creación formateada según configuración de la empresa */
    public function getFormattedCreatedAtAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);
        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    /** Fecha del documento formateada */
    public function getFormattedProformaInvoiceDateAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);
        return Carbon::parse($this->proforma_invoice_date)->translatedFormat($dateFormat);
    }

    /** Fecha de validez/expiración formateada */
    public function getFormattedExpiryDateAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);
        return Carbon::parse($this->expiry_date)->translatedFormat($dateFormat);
    }

    /** Permite edición siempre que no esté aceptada */
    public function getAllowEditAttribute()
    {
        return $this->status !== self::STATUS_ACCEPTED;
    }

    /** Estado previo basado en flags de envío/visualización */
    public function getPreviousStatus()
    {
        if ($this->viewed) {
            return self::STATUS_VIEWED;
        } elseif ($this->sent) {
            return self::STATUS_SENT;
        }
        return self::STATUS_DRAFT;
    }

    // =====================================================================
    // SCOPES (filtros reutilizables para queries)
    // =====================================================================

    public function scopeWhereStatus($query, $status)
    {
        return $query->where('proforma_invoices.status', $status);
    }

    public function scopeWhereProformaInvoiceNumber($query, $number)
    {
        return $query->where('proforma_invoices.proforma_invoice_number', 'LIKE', '%' . $number . '%');
    }

    public function scopeProformaInvoicesBetween($query, $start, $end)
    {
        return $query->whereBetween('proforma_invoices.proforma_invoice_date', [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }

    /** Búsqueda por nombre/contacto/empresa del cliente */
    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->whereHas('customer', function ($query) use ($term) {
                $query->where('name', 'LIKE', '%' . $term . '%')
                    ->orWhere('contact_name', 'LIKE', '%' . $term . '%')
                    ->orWhere('company_name', 'LIKE', '%' . $term . '%');
            });
        }
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    /** Filtro combinado: búsqueda, estado, fechas, cliente, ordenación */
    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters)->filter()->all();

        return $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->whereSearch($search);
        })->when($filters['status'] ?? null, function ($query, $status) {
            $query->whereStatus($status);
        })->when($filters['proforma_invoice_number'] ?? null, function ($query, $number) {
            $query->whereProformaInvoiceNumber($number);
        })->when(($filters['from_date'] ?? null) && ($filters['to_date'] ?? null), function ($query) use ($filters) {
            $start = Carbon::parse($filters['from_date']);
            $end = Carbon::parse($filters['to_date']);
            $query->proformaInvoicesBetween($start, $end);
        })->when($filters['customer_id'] ?? null, function ($query, $customerId) {
            $query->where('customer_id', $customerId);
        })->when($filters['orderByField'] ?? null, function ($query, $orderByField) use ($filters) {
            $orderBy = $filters['orderBy'] ?? 'desc';
            $query->orderBy($orderByField, $orderBy);
        }, function ($query) {
            $query->orderBy('sequence_number', 'desc');
        });
    }

    public function scopeWhereCompany($query)
    {
        $query->where('proforma_invoices.company_id', request()->header('company'));
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }
        return $query->paginate($limit);
    }

    // =====================================================================
    // CRUD ESTÁTICOS — Creación, actualización y eliminación
    // =====================================================================

    /**
     * Crea una factura proforma a partir de los datos del request.
     * Genera número de secuencia, hash único y crea ítems + impuestos.
     */
    public static function extractSequenceFromNumber(?string $number): ?int
    {
        if (! $number) return null;
        if (preg_match('/(\d+)\s*$/', $number, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Onfactu: asigna número de serie a un borrador sin número.
     */
    public function assignNumber()
    {
        if (! empty($this->proforma_invoice_number)) {
            return $this;
        }

        $serial = (new SerialNumberFormatter)
            ->setModel($this)
            ->setCompany($this->company_id)
            ->setCustomer($this->customer_id)
            ->setNextNumbers();

        $this->sequence_number         = $serial->nextSequenceNumber;
        $this->proforma_invoice_number = $serial->getNextNumber();

        if (empty($this->customer_sequence_number)) {
            $this->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        }

        $this->save();

        return $this;
    }

    /**

    public static function createProformaInvoice($request)
    {
        $data = $request->getProformaInvoicePayload();

        if ($request->has('proformaInvoiceSend')) {
            $data['status'] = self::STATUS_SENT;
        }

        // Onfactu: borrador sin número.
        $savedAsDraftWithoutNumber = empty($data['proforma_invoice_number']);

        if ($savedAsDraftWithoutNumber) {
            $data['proforma_invoice_number'] = null;
            $data['sequence_number']         = null;
            $data['status']                  = self::STATUS_DRAFT;
        }

        $proformaInvoice = self::create($data);

        if (! $savedAsDraftWithoutNumber) {
            $serial = (new SerialNumberFormatter)
                ->setModel($proformaInvoice)
                ->setCompany($proformaInvoice->company_id)
                ->setCustomer($proformaInvoice->customer_id)
                ->setNextNumbers();

            $parsedSeq = self::extractSequenceFromNumber($proformaInvoice->proforma_invoice_number);
            $proformaInvoice->sequence_number = $parsedSeq !== null
                ? $parsedSeq
                : $serial->nextSequenceNumber;

            $proformaInvoice->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        }

        $proformaInvoice->unique_hash = Hashids::connection(self::class)->encode($proformaInvoice->id);
        $proformaInvoice->save();

        // Crear líneas de ítems
        self::createItems($proformaInvoice, $request->items);

        // Registrar tipo de cambio si la moneda difiere
        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));
        if ((string) $data['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($proformaInvoice);
        }

        // Crear impuestos globales del documento
        if ($request->has('taxes') && ! empty($request->taxes)) {
            self::createTaxes($proformaInvoice, $request->taxes);
        }

        // Añadir campos personalizados
        if ($request->customFields) {
            $proformaInvoice->addCustomFields($request->customFields);
        }

        return self::with(['items', 'items.fields', 'items.fields.customField', 'customer', 'taxes'])
            ->find($proformaInvoice->id);
    }

    /**
     * Actualiza la factura proforma existente.
     * Elimina ítems anteriores y los recrea con los nuevos datos.
     */
    public function updateProformaInvoice($request)
    {
        $data = $request->getProformaInvoicePayload();

        // Onfactu: detectar transición borrador-sin-número → con número.
        $wasDraftWithoutNumber = empty($this->proforma_invoice_number);
        $nowHasNumber          = ! empty($data['proforma_invoice_number'] ?? null);
        $finalizingDraft       = $wasDraftWithoutNumber && $nowHasNumber;
        $stillDraftNoNumber    = $wasDraftWithoutNumber && ! $nowHasNumber;

        if ($stillDraftNoNumber) {
            $data['proforma_invoice_number'] = null;
            $data['sequence_number']         = null;
            $data['status']                  = self::STATUS_DRAFT;
        }

        if ($finalizingDraft) {
            unset($data['sequence_number']);
        }

        $serial = (new SerialNumberFormatter)
            ->setModel($this)
            ->setCompany($this->company_id)
            ->setCustomer($request->customer_id)
            ->setModelObject($this->id)
            ->setNextNumbers();

        if (! $stillDraftNoNumber) {
            $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;
        }

        $this->update($data);

        if ($finalizingDraft) {
            $parsedSeq = self::extractSequenceFromNumber($this->proforma_invoice_number);
            $this->sequence_number = $parsedSeq !== null
                ? $parsedSeq
                : $serial->nextSequenceNumber;
            $this->save();
        }

        // Registrar tipo de cambio
        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));
        if ((string) $data['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($this);
        }

        // Eliminar ítems y campos anteriores, y recrearlos
        $this->items->map(function ($item) {
            $item->fields()->get()->map(fn ($field) => $field->delete());
        });
        $this->items()->delete();
        $this->taxes()->delete();

        self::createItems($this, $request->items);

        if ($request->has('taxes') && ! empty($request->taxes)) {
            self::createTaxes($this, $request->taxes);
        }

        if ($request->customFields) {
            $this->updateCustomFields($request->customFields);
        }

        return self::with(['items', 'items.fields', 'items.fields.customField', 'customer', 'taxes'])
            ->find($this->id);
    }

    /**
     * Crea las líneas de ítems para una proforma.
     * Calcula valores en moneda base usando el tipo de cambio.
     */
    public static function createItems($proformaInvoice, $items)
    {
        $exchange_rate = $proformaInvoice->exchange_rate;

        foreach ($items as $itemData) {
            $itemData['company_id'] = $proformaInvoice->company_id;
            $itemData['exchange_rate'] = $exchange_rate;
            $itemData['base_price'] = $itemData['price'] * $exchange_rate;
            $itemData['base_discount_val'] = $itemData['discount_val'] * $exchange_rate;
            $itemData['base_tax'] = $itemData['tax'] * $exchange_rate;
            $itemData['base_total'] = $itemData['total'] * $exchange_rate;

            $item = $proformaInvoice->items()->create($itemData);

            // Crear impuestos por línea si existen
            if (array_key_exists('taxes', $itemData) && $itemData['taxes']) {
                foreach ($itemData['taxes'] as $tax) {
                    $tax['company_id'] = $proformaInvoice->company_id;
                    $tax['exchange_rate'] = $proformaInvoice->exchange_rate;
                    $tax['base_amount'] = $tax['amount'] * $exchange_rate;
                    $tax['currency_id'] = $proformaInvoice->currency_id;

                    if (gettype($tax['amount']) !== 'NULL') {
                        $item->taxes()->create($tax);
                    }
                }
            }

            // Campos personalizados por línea
            if (array_key_exists('custom_fields', $itemData) && $itemData['custom_fields']) {
                $item->addCustomFields($itemData['custom_fields']);
            }
        }
    }

    /** Crea impuestos globales del documento */
    public static function createTaxes($proformaInvoice, $taxes)
    {
        $exchange_rate = $proformaInvoice->exchange_rate;

        foreach ($taxes as $tax) {
            $tax['company_id'] = $proformaInvoice->company_id;
            $tax['exchange_rate'] = $proformaInvoice->exchange_rate;
            $tax['base_amount'] = $tax['amount'] * $exchange_rate;
            $tax['currency_id'] = $proformaInvoice->currency_id;

            if (gettype($tax['amount']) !== 'NULL') {
                $proformaInvoice->taxes()->create($tax);
            }
        }
    }

    /** Eliminación masiva por IDs */
    public static function deleteProformaInvoices($ids)
    {
        foreach ($ids as $id) {
            $proformaInvoice = self::find($id);
            if ($proformaInvoice) {
                $proformaInvoice->delete();
            }
        }
        return true;
    }

    // =====================================================================
    // CONVERSIÓN A FACTURA REAL
    // =====================================================================

    /**
     * Convierte esta proforma en una factura real.
     * Copia todos los datos (ítems, impuestos, campos personalizados)
     * y registra la relación en converted_invoice_id.
     *
     * @return Invoice La factura creada
     */
    public function convertToInvoice()
    {
        // Crear la factura con los datos de la proforma
        $invoice = Invoice::create([
            'invoice_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(
                (int) CompanySetting::getSetting('invoice_due_date_days', $this->company_id) ?: 7
            ),
            'invoice_number' => '', // Se regenerará abajo
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'discount_type' => $this->discount_type,
            'discount' => $this->discount,
            'discount_val' => $this->discount_val,
            'sub_total' => $this->sub_total,
            'total' => $this->total,
            'tax' => $this->tax,
            'due_amount' => $this->total,
            'notes' => $this->notes,
            'reference_number' => $this->reference_number,
            'customer_id' => $this->customer_id,
            'company_id' => $this->company_id,
            'creator_id' => auth()->id(),
            'currency_id' => $this->currency_id,
            'exchange_rate' => $this->exchange_rate,
            'base_discount_val' => $this->base_discount_val,
            'base_sub_total' => $this->base_sub_total,
            'base_total' => $this->base_total,
            'base_tax' => $this->base_tax,
            'base_due_amount' => $this->base_total,
            'template_name' => $this->template_name,
            'tax_per_item' => $this->tax_per_item,
            'discount_per_item' => $this->discount_per_item,
            'sales_tax_type' => $this->sales_tax_type,
            'sales_tax_address_type' => $this->sales_tax_address_type,
        ]);

        // Generar número y secuencia de la factura
        $serial = (new SerialNumberFormatter)
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $invoice->sequence_number = $serial->nextSequenceNumber;
        $invoice->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->invoice_number = (new SerialNumberFormatter)
            ->setModel(new Invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setModelObject($invoice->id)
            ->getNextNumber();
        $invoice->save();

        // Copiar ítems de la proforma a la factura
        foreach ($this->items as $item) {
            $newItem = $invoice->items()->create($item->toArray());

            // Copiar impuestos del ítem
            foreach ($item->taxes as $tax) {
                $newItem->taxes()->create($tax->toArray());
            }
        }

        // Copiar impuestos globales
        foreach ($this->taxes as $tax) {
            $invoice->taxes()->create($tax->toArray());
        }

        // Marcar la proforma como convertida
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'converted_invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }

    // =====================================================================
    // GENERACIÓN DE PDF
    // =====================================================================

    /**
     * Genera los datos para el PDF de la factura proforma.
     * Usa las plantillas de la carpeta proforma-invoice/ o reutiliza invoice/.
     */
    public function getPDFData()
    {
        $taxes = collect();

        // Agrupar impuestos por tipo si están configurados por ítem
        if ($this->tax_per_item === 'YES') {
            foreach ($this->items as $item) {
                foreach ($item->taxes as $tax) {
                    $found = $taxes->filter(fn ($t) => $t->tax_type_id == $tax->tax_type_id)->first();
                    if ($found) {
                        $found->amount += $tax->amount;
                    } else {
                        $taxes->push($tax);
                    }
                }
            }
        }

        $template_name = self::find($this->id)->template_name;
        $company = Company::find($this->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);
        $customFields = CustomField::where('model_type', 'Item')->get();

        App::setLocale($locale);

        $logo = $company->logo_path;

        // Compartir variables con la vista Blade
        // Nota: usamos 'invoice' como nombre de variable para compatibilidad
        // con las plantillas existentes (invoice4.blade.php, etc.)
        view()->share([
            'invoice' => $this,
            'customFields' => $customFields,
            'company_address' => $this->getCompanyAddress(),
            'shipping_address' => $this->getCustomerShippingAddress(),
            'billing_address' => $this->getCustomerBillingAddress(),
            'notes' => $this->getNotes(),
            'logo' => $logo ?? null,
            'taxes' => $taxes,
            'is_proforma' => true, // Flag para que la plantilla muestre "FACTURA PROFORMA"
        ]);

        // Buscar plantilla en la carpeta proforma-invoice/; si no existe, usar invoice/
        $template = PdfTemplateUtils::findFormattedTemplate('proforma-invoice', $template_name, '');
        if ($template) {
            $templatePath = $template['custom']
                ? sprintf('pdf_templates::proforma-invoice.%s', $template_name)
                : sprintf('app.pdf.proforma-invoice.%s', $template_name);
        } else {
            // Fallback: usar plantillas de factura normal
            $template = PdfTemplateUtils::findFormattedTemplate('invoice', $template_name, '');
            $templatePath = $template['custom']
                ? sprintf('pdf_templates::invoice.%s', $template_name)
                : sprintf('app.pdf.invoice.%s', $template_name);
        }

        if (request()->has('preview')) {
            return view($templatePath);
        }

        return PDF::loadView($templatePath);
    }

    // =====================================================================
    // DIRECCIONES FORMATEADAS
    // =====================================================================

    public function getCompanyAddress()
    {
        if ($this->company && ! $this->company->address()->exists()) {
            return false;
        }
        $format = CompanySetting::getSetting('invoice_company_address_format', $this->company_id);
        return $this->getFormattedString($format);
    }

    public function getCustomerShippingAddress()
    {
        if ($this->customer && ! $this->customer->shippingAddress()->exists()) {
            return false;
        }
        $format = CompanySetting::getSetting('invoice_shipping_address_format', $this->company_id);
        return $this->getFormattedString($format);
    }

    public function getCustomerBillingAddress()
    {
        if ($this->customer && ! $this->customer->billingAddress()->exists()) {
            return false;
        }
        $format = CompanySetting::getSetting('invoice_billing_address_format', $this->company_id);
        return $this->getFormattedString($format);
    }

    public function getNotes()
    {
        return $this->getFormattedString($this->notes);
    }

    public function getExtraFields()
    {
        return [
            '{PROFORMA_INVOICE_DATE}' => $this->formattedProformaInvoiceDate,
            '{PROFORMA_INVOICE_EXPIRY_DATE}' => $this->formattedExpiryDate,
            '{PROFORMA_INVOICE_NUMBER}' => $this->proforma_invoice_number,
            '{PROFORMA_INVOICE_REF_NUMBER}' => $this->reference_number,
        ];
    }
}
