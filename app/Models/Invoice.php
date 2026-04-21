<?php

namespace App\Models;

use App;
use App\Facades\PDF;
use App\Mail\SendInvoiceMail;
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
use Nwidart\Modules\Facades\Module;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Vinkla\Hashids\Facades\Hashids;

class Invoice extends Model implements HasMedia
{
    use GeneratesPdfTrait;
    use HasCustomFieldsTrait;
    use HasFactory;
    use InteractsWithMedia;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SENT = 'SENT';

    public const STATUS_VIEWED = 'VIEWED';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_UNPAID = 'UNPAID';

    public const STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';

    public const STATUS_PAID = 'PAID';

    public const STATUS_APPROVED = 'APPROVED';

    public const VERIFACTU_PENDING = 'PENDING';

    public const VERIFACTU_SIGNED = 'SIGNED';

    public const VERIFACTU_ERROR = 'ERROR';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'invoice_date',
        'due_date',
    ];

    protected $guarded = [
        'id',
    ];

    protected $appends = [
        'formattedCreatedAt',
        'formattedInvoiceDate',
        'formattedDueDate',
        'invoicePdfUrl',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany('App\Models\EmailLog', 'mailable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Models\InvoiceItem::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function getInvoicePdfUrlAttribute()
    {
        return url('/invoices/pdf/'.$this->unique_hash);
    }

    public function getPaymentModuleEnabledAttribute()
    {
        if (Module::has('Payments')) {
            return Module::isEnabled('Payments');
        }

        return false;
    }

    public function getAllowEditAttribute()
    {
        $retrospective_edit = CompanySetting::getSetting('retrospective_edits', $this->company_id);

        $allowed = true;

        $status = [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_VIEWED,
            self::STATUS_COMPLETED,
        ];

        if ($retrospective_edit == 'disable_on_invoice_sent' && (in_array($this->status, $status)) && ($this->paid_status === Invoice::STATUS_PARTIALLY_PAID || $this->paid_status === Invoice::STATUS_PAID)) {
            $allowed = false;
        } elseif ($retrospective_edit == 'disable_on_invoice_partial_paid' && ($this->paid_status === Invoice::STATUS_PARTIALLY_PAID || $this->paid_status === Invoice::STATUS_PAID)) {
            $allowed = false;
        } elseif ($retrospective_edit == 'disable_on_invoice_paid' && $this->paid_status === Invoice::STATUS_PAID) {
            $allowed = false;
        }

        // Facturas aprobadas en VeriFactu no se pueden editar
        if ($this->status === self::STATUS_APPROVED || $this->verifactu_status === self::VERIFACTU_PENDING || $this->verifactu_status === self::VERIFACTU_SIGNED) {
            $allowed = false;
        }

        return $allowed;
    }

    public function getPreviousStatus()
    {
        if ($this->viewed) {
            return self::STATUS_VIEWED;
        } elseif ($this->sent) {
            return self::STATUS_SENT;
        } else {
            return self::STATUS_DRAFT;
        }
    }

    public function getFormattedNotesAttribute($value)
    {
        return $this->getNotes();
    }

    public function getFormattedCreatedAtAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function getFormattedDueDateAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->due_date)->translatedFormat($dateFormat);
    }

    public function getFormattedInvoiceDateAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);
        $timeFormat = CompanySetting::getSetting('carbon_time_format', $this->company_id);
        $invoiceTimeEnabled = CompanySetting::getSetting('invoice_use_time', $this->company_id);

        if ($invoiceTimeEnabled === 'YES') {
            $dateFormat .= ' '.$timeFormat;
        }

        return Carbon::parse($this->invoice_date)->translatedFormat($dateFormat);
    }

    public function scopeWhereStatus($query, $status)
    {
        return $query->where('invoices.status', $status);
    }

    public function scopeWherePaidStatus($query, $status)
    {
        return $query->where('invoices.paid_status', $status);
    }

    public function scopeWhereDueStatus($query, $status)
    {
        return $query->whereIn('invoices.paid_status', [
            self::STATUS_UNPAID,
            self::STATUS_PARTIALLY_PAID,
        ]);
    }

    public function scopeWhereInvoiceNumber($query, $invoiceNumber)
    {
        return $query->where('invoices.invoice_number', 'LIKE', '%'.$invoiceNumber.'%');
    }

    public function scopeInvoicesBetween($query, $start, $end)
    {
        return $query->whereBetween(
            'invoices.invoice_date',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->whereHas('customer', function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('contact_name', 'LIKE', '%'.$term.'%')
                    ->orWhere('company_name', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters)->filter()->all();

        return $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->whereSearch($search);
        })->when($filters['status'] ?? null, function ($query, $status) {
            match ($status) {
                self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID => $query->wherePaidStatus($status),
                'DUE' => $query->whereDueStatus($status),
                default => $query->whereStatus($status),
            };
        })->when($filters['paid_status'] ?? null, function ($query, $paidStatus) {
            $query->wherePaidStatus($paidStatus);
        })->when($filters['invoice_id'] ?? null, function ($query, $invoiceId) {
            $query->whereInvoice($invoiceId);
        })->when($filters['invoice_number'] ?? null, function ($query, $invoiceNumber) {
            $query->whereInvoiceNumber($invoiceNumber);
        })->when(($filters['from_date'] ?? null) && ($filters['to_date'] ?? null), function ($query) use ($filters) {
            $start = Carbon::parse($filters['from_date']);
            $end = Carbon::parse($filters['to_date']);
            $query->invoicesBetween($start, $end);
        })->when($filters['customer_id'] ?? null, function ($query, $customerId) {
            $query->where('customer_id', $customerId);
        })->when($filters['orderByField'] ?? null, function ($query, $orderByField) use ($filters) {
            $orderBy = $filters['orderBy'] ?? 'desc';
            $query->orderBy($orderByField, $orderBy);
        }, function ($query) {
            $query->orderBy('sequence_number', 'desc');
        });
    }

    public function scopeWhereInvoice($query, $invoice_id)
    {
        $query->orWhere('id', $invoice_id);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('invoices.company_id', request()->header('company'));
    }

    public function scopeWhereCompanyId($query, $company)
    {
        $query->where('invoices.company_id', $company);
    }

    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->where('invoices.customer_id', $customer_id);
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public static function createInvoice($request)
    {
        $data = $request->getInvoicePayload();

        if ($request->has('invoiceSend')) {
            $data['status'] = Invoice::STATUS_SENT;
        }

        // Numeración diferida (Onfactu):
        // - invoice_number y sequence_number quedan NULL en borrador
        // - Si el usuario ha escrito un invoice_number manualmente, se respeta
        //   (viene en $data). sequence_number sigue NULL para no afectar al
        //   contador automático.
        // - El número se asignará al APROBAR la factura (VeriFactu).
        if (empty($data['invoice_number'])) {
            $data['invoice_number'] = null;
        }

        $invoice = Invoice::create($data);

        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();

        self::createItems($invoice, $request->items);

        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($invoice);
        }

        if ($request->has('taxes') && (! empty($request->taxes))) {
            self::createTaxes($invoice, $request->taxes);
        }

        if ($request->customFields) {
            $invoice->addCustomFields($request->customFields);
        }

        $invoice = Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
        ])
            ->find($invoice->id);

        return $invoice;
    }

    public function updateInvoice($request)
    {
        // Numeración diferida (Onfactu):
        // - No recalculamos sequence_number en update. Solo se asigna al aprobar.
        // - Si el usuario cambió el invoice_number manualmente en borrador,
        //   lo respetamos tal cual (ya validado como único en InvoicesRequest).
        // - Si el invoice_number viene vacío, se guarda NULL (sigue en borrador).

        $data = $request->getInvoicePayload();

        if (empty($data['invoice_number'])) {
            $data['invoice_number'] = null;
        }

        $oldTotal = $this->total;

        $total_paid_amount = $this->total - $this->due_amount;

        if ($total_paid_amount > 0 && $this->customer_id !== $request->customer_id) {
            return 'customer_cannot_be_changed_after_payment_is_added';
        }

        if ($request->total >= 0 && $request->total < $total_paid_amount) {
            return 'total_invoice_amount_must_be_more_than_paid_amount';
        }

        if ($oldTotal != $request->total) {
            $oldTotal = (int) round($request->total) - (int) $oldTotal;
        } else {
            $oldTotal = 0;
        }

        $data['due_amount'] = ($this->due_amount + $oldTotal);
        $data['base_due_amount'] = $data['due_amount'] * $data['exchange_rate'];
        // customer_sequence_number ya no se recalcula aquí; se asignará al aprobar.

        $this->update($data);

        $statusData = $this->getInvoiceStatusByAmount($data['due_amount']);
        if (! empty($statusData)) {
            $this->update($statusData);
        }

        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($this);
        }

        $this->items->map(function ($item) {
            $fields = $item->fields()->get();

            $fields->map(function ($field) {
                $field->delete();
            });
        });

        $this->items()->delete();
        $this->taxes()->delete();

        self::createItems($this, $request->items);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            self::createTaxes($this, $request->taxes);
        }

        if ($request->customFields) {
            $this->updateCustomFields($request->customFields);
        }

        $invoice = Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
        ])
            ->find($this->id);

        return $invoice;
    }

    /**
     * Asigna número de factura — Onfactu numeración diferida.
     *
     * Se invoca al APROBAR la factura (firma VeriFactu).
     *
     * Comportamiento:
     *  - Si la factura ya tiene invoice_number (manual del usuario), se respeta.
     *    Solo se valida unicidad; si colisiona, lanza excepción con datos del
     *    documento conflictivo.
     *  - Si no tiene invoice_number, se genera automáticamente calculando el
     *    siguiente sequence_number. Si el número formateado resultante colisiona
     *    con otro existente (normalmente un borrador con número manual), NO se
     *    salta al siguiente: lanza excepción indicando qué factura tiene el
     *    número ocupado, para que el usuario resuelva el conflicto manualmente
     *    (editándola o eliminándola). Esto garantiza que la numeración quede
     *    siempre estrictamente correlativa en los números automáticos.
     *
     * Toda la operación ocurre dentro de una transacción con lock pesimista
     * sobre los registros implicados, para evitar que dos aprobaciones
     * simultáneas asignen el mismo número.
     *
     * @return self
     *
     * @throws NumberCollisionException Si hay colisión de número, con el
     *         ID y número del documento conflictivo en el array details.
     */
    public function assignNumber(): self
    {
        return DB::transaction(function () {
            // 1) Si ya tiene invoice_number (manual), validamos unicidad.
            //    No tocamos sequence_number: queda NULL, no afecta al contador
            //    automático. Los números manuales no "consumen" secuenciales.
            if (! empty($this->invoice_number)) {
                $conflict = Invoice::where('company_id', $this->company_id)
                    ->where('invoice_number', $this->invoice_number)
                    ->where('id', '<>', $this->id)
                    ->lockForUpdate()
                    ->first();

                if ($conflict) {
                    throw new \App\Exceptions\NumberCollisionException(
                        "El número de factura '{$this->invoice_number}' ya existe en esta empresa.",
                        [
                            'conflicting_id' => $conflict->id,
                            'conflicting_number' => $conflict->invoice_number,
                            'conflicting_status' => $conflict->status,
                            'attempted_number' => $this->invoice_number,
                        ]
                    );
                }

                return $this->fresh();
            }

            // 2) Generación automática: siguiente sequence_number disponible.
            //    Si el número formateado colisiona con otro existente, NO saltamos:
            //    lanzamos error para que el usuario resuelva el conflicto.
            $lastAuto = Invoice::where('company_id', $this->company_id)
                ->whereNotNull('sequence_number')
                ->lockForUpdate()
                ->max('sequence_number');

            $nextSeq = ($lastAuto ? (int) $lastAuto : 0) + 1;
            $candidate = $this->formatSerialForSequence($nextSeq);

            // Comprobar si el candidato ya existe (típicamente, un borrador con
            // número manual coincidente).
            $conflict = Invoice::where('company_id', $this->company_id)
                ->where('invoice_number', $candidate)
                ->where('id', '<>', $this->id)
                ->lockForUpdate()
                ->first();

            if ($conflict) {
                throw new \App\Exceptions\NumberCollisionException(
                    "Ya existe una factura con el número '{$candidate}' (estado: {$conflict->status}). "
                    . 'Para continuar, edita esa factura y cambia o libera su número, o elimínala.',
                    [
                        'conflicting_id' => $conflict->id,
                        'conflicting_number' => $conflict->invoice_number,
                        'conflicting_status' => $conflict->status,
                        'attempted_number' => $candidate,
                    ]
                );
            }

            // OK: asignar número automático
            $this->invoice_number = $candidate;
            $this->sequence_number = $nextSeq;

            // customer_sequence_number: siguiente libre para este cliente
            $lastCustSeq = Invoice::where('company_id', $this->company_id)
                ->where('customer_id', $this->customer_id)
                ->whereNotNull('customer_sequence_number')
                ->max('customer_sequence_number');
            $this->customer_sequence_number = ($lastCustSeq ? (int) $lastCustSeq : 0) + 1;

            $this->save();

            return $this->fresh();
        });
    }

    /**
     * Formatea un invoice_number para un sequence_number concreto, usando el
     * formato configurado en la empresa.
     */
    protected function formatSerialForSequence(int $sequence): string
    {
        $format = CompanySetting::getSetting('invoice_number_format', $this->company_id)
            ?: '{{SERIES:INV}}{{DELIMITER:-}}{{SEQUENCE:6}}';

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

    public function sendInvoiceData($data)
    {
        $data['invoice'] = $this->toArray();
        $data['customer'] = $this->customer->toArray();
        $data['company'] = Company::find($this->company_id);
        $data['subject'] = $this->getEmailString($data['subject']);
        $data['body'] = $this->getEmailString($data['body']);
        $data['attach']['data'] = ($this->getEmailAttachmentSetting()) ? $this->getPDFData() : null;

        return $data;
    }

    public function preview($data)
    {
        $data = $this->sendInvoiceData($data);

        return [
            'type' => 'preview',
            'view' => new SendInvoiceMail($data),
        ];
    }

    public function send($data)
    {
        $data = $this->sendInvoiceData($data);

        \Mail::to($data['to'])->send(new SendInvoiceMail($data));

        if ($this->status == Invoice::STATUS_DRAFT) {
            $this->status = Invoice::STATUS_SENT;
            $this->sent = true;
            $this->save();
        }

        return [
            'success' => true,
            'type' => 'send',
        ];
    }

    public static function createItems($invoice, $invoiceItems)
    {
        $exchange_rate = $invoice->exchange_rate;

        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItem['company_id'] = $invoice->company_id;
            $invoiceItem['exchange_rate'] = $exchange_rate;
            $invoiceItem['base_price'] = $invoiceItem['price'] * $exchange_rate;
            $invoiceItem['base_discount_val'] = $invoiceItem['discount_val'] * $exchange_rate;
            $invoiceItem['base_tax'] = $invoiceItem['tax'] * $exchange_rate;
            $invoiceItem['base_total'] = $invoiceItem['total'] * $exchange_rate;

            if (array_key_exists('recurring_invoice_id', $invoiceItem)) {
                unset($invoiceItem['recurring_invoice_id']);
            }

            $item = $invoice->items()->create($invoiceItem);

            if (array_key_exists('taxes', $invoiceItem) && $invoiceItem['taxes']) {
                foreach ($invoiceItem['taxes'] as $tax) {
                    $tax['company_id'] = $invoice->company_id;
                    $tax['exchange_rate'] = $invoice->exchange_rate;
                    $tax['base_amount'] = $tax['amount'] * $exchange_rate;
                    $tax['currency_id'] = $invoice->currency_id;

                    if (gettype($tax['amount']) !== 'NULL') {
                        if (array_key_exists('recurring_invoice_id', $invoiceItem)) {
                            unset($invoiceItem['recurring_invoice_id']);
                        }

                        $item->taxes()->create($tax);
                    }
                }
            }

            if (array_key_exists('custom_fields', $invoiceItem) && $invoiceItem['custom_fields']) {
                $item->addCustomFields($invoiceItem['custom_fields']);
            }
        }
    }

    public static function createTaxes($invoice, $taxes)
    {

        $exchange_rate = $invoice->exchange_rate;

        foreach ($taxes as $tax) {
            $tax['company_id'] = $invoice->company_id;
            $tax['exchange_rate'] = $invoice->exchange_rate;
            $tax['base_amount'] = $tax['amount'] * $exchange_rate;
            $tax['currency_id'] = $invoice->currency_id;

            if (gettype($tax['amount']) !== 'NULL') {
                if (array_key_exists('recurring_invoice_id', $tax)) {
                    unset($tax['recurring_invoice_id']);
                }

                $invoice->taxes()->create($tax);
            }
        }
    }

    public function getPDFData()
    {
        $taxes = collect();

        if ($this->tax_per_item === 'YES') {
            foreach ($this->items as $item) {
                foreach ($item->taxes as $tax) {
                    $found = $taxes->filter(function ($item) use ($tax) {
                        return $item->tax_type_id == $tax->tax_type_id;
                    })->first();

                    if ($found) {
                        $found->amount += $tax->amount;
                    } else {
                        $taxes->push($tax);
                    }
                }
            }
        }

        $invoiceTemplate = self::find($this->id)->template_name;

        $company = Company::find($this->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);
        $customFields = CustomField::where('model_type', 'Item')->get();

        App::setLocale($locale);

        $logo = $company->logo_path;

        view()->share([
            'invoice' => $this,
            'customFields' => $customFields,
            'company_address' => $this->getCompanyAddress(),
            'shipping_address' => $this->getCustomerShippingAddress(),
            'billing_address' => $this->getCustomerBillingAddress(),
            'notes' => $this->getNotes(),
            'logo' => $logo ?? null,
            'taxes' => $taxes,
            'verifactu_qr' => $this->verifactu_qr ?? null,
        ]);

        $template = PdfTemplateUtils::findFormattedTemplate('invoice', $invoiceTemplate, '');
        $templatePath = $template['custom'] ? sprintf('pdf_templates::invoice.%s', $invoiceTemplate) : sprintf('app.pdf.invoice.%s', $invoiceTemplate);

        if (request()->has('preview')) {
            return view($templatePath);
        }

        return PDF::loadView($templatePath);
    }

    public function getEmailAttachmentSetting()
    {
        $invoiceAsAttachment = CompanySetting::getSetting('invoice_email_attachment', $this->company_id);

        if ($invoiceAsAttachment == 'NO') {
            return false;
        }

        return true;
    }

    public function getCompanyAddress()
    {
        if ($this->company && (! $this->company->address()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('invoice_company_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getCustomerShippingAddress()
    {
        if ($this->customer && (! $this->customer->shippingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('invoice_shipping_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getCustomerBillingAddress()
    {
        if ($this->customer && (! $this->customer->billingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('invoice_billing_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getNotes()
    {
        return $this->getFormattedString($this->notes);
    }

    public function getEmailString($body)
    {
        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $body = strtr($body, $values);

        return preg_replace('/{(.*?)}/', '', $body);
    }

    public function getExtraFields()
    {
        return [
            '{INVOICE_DATE}' => $this->formattedInvoiceDate,
            '{INVOICE_DUE_DATE}' => $this->formattedDueDate,
            '{INVOICE_NUMBER}' => $this->invoice_number,
            '{INVOICE_REF_NUMBER}' => $this->reference_number,
        ];
    }

    public function addInvoicePayment($amount)
    {
        $this->due_amount += $amount;
        $this->base_due_amount = $this->due_amount * $this->exchange_rate;

        $this->changeInvoiceStatus($this->due_amount);
    }

    public function subtractInvoicePayment($amount)
    {
        $this->due_amount -= $amount;
        $this->base_due_amount = $this->due_amount * $this->exchange_rate;

        $this->changeInvoiceStatus($this->due_amount);
    }

    /**
     * Set the invoice status from amount.
     *
     * @return array
     */
    public function getInvoiceStatusByAmount($amount)
    {
        if ($amount < 0) {
            return [];
        }

        if ($amount == 0) {
            $data = [
                'status' => Invoice::STATUS_COMPLETED,
                'paid_status' => Invoice::STATUS_PAID,
                'overdue' => false,
            ];
        } elseif ($amount == $this->total) {
            $data = [
                'status' => $this->getPreviousStatus(),
                'paid_status' => Invoice::STATUS_UNPAID,
            ];
        } else {
            $data = [
                'status' => $this->getPreviousStatus(),
                'paid_status' => Invoice::STATUS_PARTIALLY_PAID,
            ];
        }

        return $data;
    }

    /**
     * Changes the invoice status right away
     *
     * @return string[]|void
     */
    public function changeInvoiceStatus($amount)
    {
        $status = $this->getInvoiceStatusByAmount($amount);
        if (! empty($status)) {
            foreach ($status as $key => $value) {
                $this->setAttribute($key, $value);
            }
            $this->save();
        }
    }

    public static function deleteInvoices($ids)
    {
        foreach ($ids as $id) {
            $invoice = self::find($id);

            if ($invoice->transactions()->exists()) {
                $invoice->transactions()->delete();
            }

            $invoice->delete();
        }

        return true;
    }
}
