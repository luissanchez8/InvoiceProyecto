<?php

/**
 * Modelo DeliveryNote — Albarán de entrega
 *
 * Representa un albarán (documento de entrega de mercancía).
 * Tiene la misma estructura de datos que una factura pero NO gestiona pagos.
 * El campo show_prices controla si los precios se muestran en el PDF.
 *
 * Estados posibles: DRAFT → SENT → DELIVERED
 *
 * Relaciones principales:
 *   - items()    → DeliveryNoteItem (líneas del documento)
 *   - taxes()    → Tax (impuestos aplicados)
 *   - customer() → Customer (cliente destinatario)
 *   - company()  → Company (empresa emisora)
 *   - creator()  → User (usuario que creó el documento)
 *   - currency() → Currency (moneda del documento)
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
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Vinkla\Hashids\Facades\Hashids;

class DeliveryNote extends Model implements HasMedia
{
    use GeneratesPdfTrait;
    use HasCustomFieldsTrait;
    use HasFactory;
    use InteractsWithMedia;

    // --- Constantes de estado ---
    public const STATUS_DRAFT = 'DRAFT';         // Borrador
    public const STATUS_SENT = 'SENT';           // Enviado al cliente
    public const STATUS_DELIVERED = 'DELIVERED'; // Entregado

    protected $table = 'delivery_notes';

    protected $dates = [
        'created_at',
        'updated_at',
        'delivery_note_date',
        'delivery_date',
    ];

    protected $guarded = ['id'];

    protected $appends = [
        'formattedCreatedAt',
        'formattedDeliveryNoteDate',
        'formattedDeliveryDate',
        'deliveryNotePdfUrl',
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
            'show_prices' => 'boolean', // Controla visibilidad de precios en PDF
        ];
    }

    // =====================================================================
    // RELACIONES
    // =====================================================================

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'mailable');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // =====================================================================
    // ATRIBUTOS COMPUTADOS
    // =====================================================================

    public function getDeliveryNotePdfUrlAttribute()
    {
        return url('/delivery-notes/pdf/' . $this->unique_hash);
    }

    public function getFormattedCreatedAtAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);
        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function getFormattedDeliveryNoteDateAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);
        return Carbon::parse($this->delivery_note_date)->translatedFormat($dateFormat);
    }

    public function getFormattedDeliveryDateAttribute()
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);
        return Carbon::parse($this->delivery_date)->translatedFormat($dateFormat);
    }

    /** Permite edición siempre que no esté entregado */
    public function getAllowEditAttribute()
    {
        return $this->status !== self::STATUS_DELIVERED;
    }

    public function getPreviousStatus()
    {
        if ($this->sent) {
            return self::STATUS_SENT;
        }
        return self::STATUS_DRAFT;
    }

    // =====================================================================
    // SCOPES
    // =====================================================================

    public function scopeWhereStatus($query, $status)
    {
        return $query->where('delivery_notes.status', $status);
    }

    public function scopeWhereDeliveryNoteNumber($query, $number)
    {
        return $query->where('delivery_notes.delivery_note_number', 'LIKE', '%' . $number . '%');
    }

    public function scopeDeliveryNotesBetween($query, $start, $end)
    {
        return $query->whereBetween('delivery_notes.delivery_note_date', [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ]);
    }

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

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters)->filter()->all();

        return $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->whereSearch($search);
        })->when($filters['status'] ?? null, function ($query, $status) {
            $query->whereStatus($status);
        })->when($filters['delivery_note_number'] ?? null, function ($query, $number) {
            $query->whereDeliveryNoteNumber($number);
        })->when(($filters['from_date'] ?? null) && ($filters['to_date'] ?? null), function ($query) use ($filters) {
            $start = Carbon::parse($filters['from_date']);
            $end = Carbon::parse($filters['to_date']);
            $query->deliveryNotesBetween($start, $end);
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
        $query->where('delivery_notes.company_id', request()->header('company'));
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }
        return $query->paginate($limit);
    }

    // =====================================================================
    // CRUD ESTÁTICOS
    // =====================================================================

    public static function createDeliveryNote($request)
    {
        $data = $request->getDeliveryNotePayload();

        if ($request->has('deliveryNoteSend')) {
            $data['status'] = self::STATUS_SENT;
        }

        // Onfactu — numeración diferida:
        // delivery_note_number y sequence_number quedan NULL en borrador.
        // El número se asignará al ENVIAR el albarán (DRAFT → SENT).
        if (empty($data['delivery_note_number'])) {
            $data['delivery_note_number'] = null;
        }

        $deliveryNote = self::create($data);

        $deliveryNote->unique_hash = Hashids::connection(self::class)->encode($deliveryNote->id);
        $deliveryNote->save();

        self::createItems($deliveryNote, $request->items);

        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));
        if ((string) $data['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($deliveryNote);
        }

        if ($request->has('taxes') && ! empty($request->taxes)) {
            self::createTaxes($deliveryNote, $request->taxes);
        }

        if ($request->customFields) {
            $deliveryNote->addCustomFields($request->customFields);
        }

        return self::with(['items', 'items.fields', 'items.fields.customField', 'customer', 'taxes'])
            ->find($deliveryNote->id);
    }

    public function updateDeliveryNote($request)
    {
        // Onfactu — numeración diferida: no recalculamos sequence_number.
        $data = $request->getDeliveryNotePayload();

        if (empty($data['delivery_note_number'])) {
            $data['delivery_note_number'] = null;
        }

        $this->update($data);

        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));
        if ((string) $data['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($this);
        }

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
     * Asigna número de albarán — Onfactu numeración diferida.
     *
     * Se invoca al ENVIAR el albarán (DRAFT → SENT).
     */
    public function assignNumber(): self
    {
        return DB::transaction(function () {
            // Lock serializado por empresa (ver acquireNumberLock).
            $this->acquireNumberLock();

            if (! empty($this->delivery_note_number)) {
                $conflict = DeliveryNote::where('company_id', $this->company_id)
                    ->where('delivery_note_number', $this->delivery_note_number)
                    ->where('id', '<>', $this->id)
                    ->first();

                if ($conflict) {
                    throw new \App\Exceptions\NumberCollisionException(
                        "El número de albarán '{$this->delivery_note_number}' ya existe en esta empresa.",
                        [
                            'conflicting_id' => $conflict->id,
                            'conflicting_number' => $conflict->delivery_note_number,
                            'conflicting_status' => $conflict->status,
                            'attempted_number' => $this->delivery_note_number,
                        ]
                    );
                }

                return $this->fresh();
            }

            $lastAuto = DeliveryNote::where('company_id', $this->company_id)
                ->whereNotNull('sequence_number')
                ->max('sequence_number');

            $nextSeq = ($lastAuto ? (int) $lastAuto : 0) + 1;
            $candidate = $this->formatSerialForSequence($nextSeq);

            $conflict = DeliveryNote::where('company_id', $this->company_id)
                ->where('delivery_note_number', $candidate)
                ->where('id', '<>', $this->id)
                ->first();

            if ($conflict) {
                throw new \App\Exceptions\NumberCollisionException(
                    "Ya existe un albarán con el número '{$candidate}' (estado: {$conflict->status}). "
                    . 'Para continuar, edita ese albarán y cambia o libera su número, o elimínalo.',
                    [
                        'conflicting_id' => $conflict->id,
                        'conflicting_number' => $conflict->delivery_note_number,
                        'conflicting_status' => $conflict->status,
                        'attempted_number' => $candidate,
                    ]
                );
            }

            $this->delivery_note_number = $candidate;
            $this->sequence_number = $nextSeq;

            $lastCustSeq = DeliveryNote::where('company_id', $this->company_id)
                ->where('customer_id', $this->customer_id)
                ->whereNotNull('customer_sequence_number')
                ->max('customer_sequence_number');
            $this->customer_sequence_number = ($lastCustSeq ? (int) $lastCustSeq : 0) + 1;

            $this->save();

            return $this->fresh();
        });
    }

    /**
     * Lock serializado por empresa. Ver Invoice::acquireNumberLock().
     */
    protected function acquireNumberLock(): void
    {
        $driver = DB::connection()->getDriverName();
        $companyId = (int) $this->company_id;

        if ($driver === 'pgsql') {
            $tableKey = crc32('delivery_notes');
            DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [$tableKey, $companyId]);
        } elseif ($driver === 'mysql') {
            $lockName = "delivery_notes_numbering_{$companyId}";
            DB::statement('SELECT GET_LOCK(?, 10)', [$lockName]);
        }
    }

    /**
     * Formatea un delivery_note_number para un sequence_number concreto.
     */
    protected function formatSerialForSequence(int $sequence): string
    {
        $format = CompanySetting::getSetting('deliverynote_number_format', $this->company_id)
            ?: '{{SERIES:ALB}}{{DELIMITER:-}}{{SEQUENCE:6}}';

        $placeholders = SerialNumberFormatter::getPlaceholders($format);

        $result = '';
        foreach ($placeholders as $p) {
            $name = $p['name'];
            $value = $p['value'];

            switch ($name) {
                case 'SEQUENCE':
                    $value = $value ?: 6;
                    $result .= str_pad((string) $sequence, (int) $value, '0', STR_PAD_LEFT);
                    break;
                case 'DATE_FORMAT':
                    $result .= date($value ?: 'Y');
                    break;
                case 'RANDOM_SEQUENCE':
                    $value = $value ?: 6;
                    $result .= substr(bin2hex(random_bytes((int) $value)), 0, (int) $value);
                    break;
                case 'CUSTOMER_SERIES':
                    $result .= ($this->customer && $this->customer->prefix) ? $this->customer->prefix : 'CST';
                    break;
                case 'CUSTOMER_SEQUENCE':
                    $result .= str_pad((string) ($this->customer_sequence_number ?? 1), (int) ($value ?: 6), '0', STR_PAD_LEFT);
                    break;
                default:
                    $result .= $value;
            }
        }

        return $result;
    }

    public static function createItems($deliveryNote, $items)
    {
        $exchange_rate = $deliveryNote->exchange_rate;

        foreach ($items as $itemData) {
            $itemData['company_id'] = $deliveryNote->company_id;
            $itemData['exchange_rate'] = $exchange_rate;
            $itemData['base_price'] = $itemData['price'] * $exchange_rate;
            $itemData['base_discount_val'] = $itemData['discount_val'] * $exchange_rate;
            $itemData['base_tax'] = $itemData['tax'] * $exchange_rate;
            $itemData['base_total'] = $itemData['total'] * $exchange_rate;

            $item = $deliveryNote->items()->create($itemData);

            if (array_key_exists('taxes', $itemData) && $itemData['taxes']) {
                foreach ($itemData['taxes'] as $tax) {
                    $tax['company_id'] = $deliveryNote->company_id;
                    $tax['exchange_rate'] = $deliveryNote->exchange_rate;
                    $tax['base_amount'] = $tax['amount'] * $exchange_rate;
                    $tax['currency_id'] = $deliveryNote->currency_id;

                    if (gettype($tax['amount']) !== 'NULL') {
                        $item->taxes()->create($tax);
                    }
                }
            }

            if (array_key_exists('custom_fields', $itemData) && $itemData['custom_fields']) {
                $item->addCustomFields($itemData['custom_fields']);
            }
        }
    }

    public static function createTaxes($deliveryNote, $taxes)
    {
        $exchange_rate = $deliveryNote->exchange_rate;

        foreach ($taxes as $tax) {
            $tax['company_id'] = $deliveryNote->company_id;
            $tax['exchange_rate'] = $deliveryNote->exchange_rate;
            $tax['base_amount'] = $tax['amount'] * $exchange_rate;
            $tax['currency_id'] = $deliveryNote->currency_id;

            if (gettype($tax['amount']) !== 'NULL') {
                $deliveryNote->taxes()->create($tax);
            }
        }
    }

    public static function deleteDeliveryNotes($ids)
    {
        foreach ($ids as $id) {
            $deliveryNote = self::find($id);
            if ($deliveryNote) {
                $deliveryNote->delete();
            }
        }
        return true;
    }

    // =====================================================================
    // GENERACIÓN DE PDF
    // =====================================================================

    /**
     * Genera los datos para el PDF del albarán.
     * La variable show_prices se pasa a la vista para controlar
     * si los precios, impuestos y totales se muestran en el PDF.
     */
    public function getPDFData()
    {
        $taxes = collect();

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
        // Usamos 'invoice' para compatibilidad con las plantillas existentes
        view()->share([
            'invoice' => $this,
            'customFields' => $customFields,
            'company_address' => $this->getCompanyAddress(),
            'shipping_address' => $this->getCustomerShippingAddress(),
            'billing_address' => $this->getCustomerBillingAddress(),
            'notes' => $this->getNotes(),
            'logo' => $logo ?? null,
            'taxes' => $taxes,
            'is_delivery_note' => true,           // Flag para título "ALBARÁN"
            'show_prices' => $this->show_prices,  // Controla visibilidad de precios en PDF
        ]);

        // Buscar plantilla en delivery-note/; si no existe, usar invoice/
        $template = PdfTemplateUtils::findFormattedTemplate('delivery-note', $template_name, '');
        if ($template) {
            $templatePath = $template['custom']
                ? sprintf('pdf_templates::delivery-note.%s', $template_name)
                : sprintf('app.pdf.delivery-note.%s', $template_name);
        } else {
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
            '{DELIVERY_NOTE_DATE}' => $this->formattedDeliveryNoteDate,
            '{DELIVERY_DATE}' => $this->formattedDeliveryDate,
            '{DELIVERY_NOTE_NUMBER}' => $this->delivery_note_number,
            '{DELIVERY_NOTE_REF_NUMBER}' => $this->reference_number,
        ];
    }
}
