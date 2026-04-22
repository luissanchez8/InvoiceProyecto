<?php

namespace App\Models;

use App;
use App\Facades\PDF;
use App\Mail\SendEstimateMail;
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
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Vinkla\Hashids\Facades\Hashids;

class Estimate extends Model implements HasMedia
{
    use GeneratesPdfTrait;
    use HasCustomFieldsTrait;
    use HasFactory;
    use InteractsWithMedia;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SENT = 'SENT';

    public const STATUS_VIEWED = 'VIEWED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'estimate_date',
        'expiry_date',
    ];

    protected $appends = [
        'formattedExpiryDate',
        'formattedEstimateDate',
        'estimatePdfUrl',
    ];

    protected $guarded = ['id'];

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

    public function getEstimatePdfUrlAttribute()
    {
        return url('/estimates/pdf/'.$this->unique_hash);
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany('App\Models\EmailLog', 'mailable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Models\EstimateItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'creator_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function getFormattedExpiryDateAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->expiry_date)->translatedFormat($dateFormat);
    }

    public function getFormattedEstimateDateAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->estimate_date)->translatedFormat($dateFormat);
    }

    public function scopeEstimatesBetween($query, $start, $end)
    {
        return $query->whereBetween(
            'estimates.estimate_date',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereStatus($query, $status)
    {
        return $query->where('estimates.status', $status);
    }

    public function scopeWhereEstimateNumber($query, $estimateNumber)
    {
        return $query->where('estimates.estimate_number', 'LIKE', '%'.$estimateNumber.'%');
    }

    public function scopeWhereEstimate($query, $estimate_id)
    {
        $query->orWhere('id', $estimate_id);
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

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('estimate_number')) {
            $query->whereEstimateNumber($filters->get('estimate_number'));
        }

        if ($filters->get('status')) {
            $query->whereStatus($filters->get('status'));
        }

        if ($filters->get('estimate_id')) {
            $query->whereEstimate($filters->get('estimate_id'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->estimatesBetween($start, $end);
        }

        if ($filters->get('customer_id')) {
            $query->whereCustomer($filters->get('customer_id'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'sequence_number';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'desc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('estimates.company_id', request()->header('company'));
    }

    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->where('estimates.customer_id', $customer_id);
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public static function createEstimate($request)
    {
        $data = $request->getEstimatePayload();

        if ($request->has('estimateSend')) {
            $data['status'] = self::STATUS_SENT;
        }

        // Onfactu — numeración diferida:
        // estimate_number y sequence_number quedan NULL en borrador.
        // Si el usuario ha escrito un número manualmente, se respeta.
        // El número se asignará al ENVIAR el presupuesto (DRAFT → SENT).
        if (empty($data['estimate_number'])) {
            $data['estimate_number'] = null;
        }

        $estimate = self::create($data);
        $estimate->unique_hash = Hashids::connection(Estimate::class)->encode($estimate->id);
        $estimate->save();

        $company_currency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $company_currency) {
            ExchangeRateLog::addExchangeRateLog($estimate);
        }

        self::createItems($estimate, $request, $estimate->exchange_rate);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            self::createTaxes($estimate, $request, $estimate->exchange_rate);
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $estimate->addCustomFields($customFields);
        }

        return $estimate;
    }

    public function updateEstimate($request)
    {
        $data = $request->getEstimatePayload();

        // Onfactu — numeración diferida:
        // No recalculamos sequence_number en update. Se asigna al enviar.
        // Si el usuario dejó vacío el estimate_number, se guarda NULL.
        if (empty($data['estimate_number'])) {
            $data['estimate_number'] = null;
        }

        $this->update($data);

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

        self::createItems($this, $request, $this->exchange_rate);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            self::createTaxes($this, $request, $this->exchange_rate);
        }

        if ($request->customFields) {
            $this->updateCustomFields($request->customFields);
        }

        return Estimate::with([
            'items.taxes',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
        ])
            ->find($this->id);
    }

    /**
     * Asigna número de presupuesto — Onfactu numeración diferida.
     *
     * Se invoca al ENVIAR el presupuesto (DRAFT → SENT). Misma lógica que
     * Invoice::assignNumber(): respeta el número manual si existe, o genera
     * uno automático. En caso de colisión, lanza NumberCollisionException
     * con los detalles del documento conflictivo (no salta al siguiente).
     *
     * Idempotente: si ya tiene número, solo valida unicidad.
     */
    public function assignNumber(): self
    {
        return DB::transaction(function () {
            $this->acquireNumberLock();

            $lastAuto = Estimate::where('company_id', $this->company_id)
                ->whereNotNull('sequence_number')
                ->max('sequence_number');

            $nextSeq = ($lastAuto ? (int) $lastAuto : 0) + 1;
            $autoCandidate = $this->formatSerialForSequence($nextSeq);

            if (! empty($this->estimate_number)) {
                $autoSeq = $this->detectAutoSequenceNumber($this->estimate_number, $nextSeq);
                $isAutoCandidate = ($autoSeq !== null);

                $conflict = Estimate::where('company_id', $this->company_id)
                    ->where('estimate_number', $this->estimate_number)
                    ->where('id', '<>', $this->id)
                    ->first();

                if ($conflict) {
                    if ($conflict->status === self::STATUS_DRAFT) {
                        throw new \App\Exceptions\NumberCollisionException(
                            "Ya existe un borrador con el número '{$this->estimate_number}'. "
                            . 'Para continuar, edita ese borrador y cambia o libera su número, o elimínalo.',
                            [
                                'conflicting_id' => $conflict->id,
                                'conflicting_number' => $conflict->estimate_number,
                                'conflicting_status' => $conflict->status,
                                'attempted_number' => $this->estimate_number,
                            ]
                        );
                    }

                    if ($isAutoCandidate) {
                        // Conflicto con emitido + era auto rellenado → saltar
                        [$newSeq, $newCandidate] = $this->findNextFreeSequence($nextSeq);
                        $this->estimate_number = $newCandidate;
                        $this->sequence_number = $newSeq;
                    } else {
                        throw new \App\Exceptions\NumberCollisionException(
                            "El número '{$this->estimate_number}' ya está asignado a un presupuesto {$conflict->status} y no puede reutilizarse.",
                            [
                                'conflicting_id' => $conflict->id,
                                'conflicting_number' => $conflict->estimate_number,
                                'conflicting_status' => $conflict->status,
                                'attempted_number' => $this->estimate_number,
                            ]
                        );
                    }
                } else {
                    if ($isAutoCandidate) {
                        $this->sequence_number = $autoSeq;
                    }
                }
            } else {
                $candidate = $autoCandidate;

                $conflict = Estimate::where('company_id', $this->company_id)
                    ->where('estimate_number', $candidate)
                    ->where('id', '<>', $this->id)
                    ->first();

                if ($conflict) {
                    if ($conflict->status === self::STATUS_DRAFT) {
                        throw new \App\Exceptions\NumberCollisionException(
                            "Ya existe un borrador con el número '{$candidate}'. "
                            . 'Para continuar, edita ese borrador y cambia o libera su número, o elimínalo.',
                            [
                                'conflicting_id' => $conflict->id,
                                'conflicting_number' => $conflict->estimate_number,
                                'conflicting_status' => $conflict->status,
                                'attempted_number' => $candidate,
                            ]
                        );
                    }
                    [$nextSeq, $candidate] = $this->findNextFreeSequence($nextSeq);
                }

                $this->estimate_number = $candidate;
                $this->sequence_number = $nextSeq;
            }

            if (empty($this->customer_sequence_number)) {
                $lastCustSeq = Estimate::where('company_id', $this->company_id)
                    ->where('customer_id', $this->customer_id)
                    ->whereNotNull('customer_sequence_number')
                    ->max('customer_sequence_number');
                $this->customer_sequence_number = ($lastCustSeq ? (int) $lastCustSeq : 0) + 1;
            }

            $this->save();

            return $this->fresh();
        });
    }

    /**
     * Busca el siguiente sequence libre saltando por encima de números
     * ya ocupados por registros emitidos.
     *
     * @return array{0:int, 1:string}
     */
    protected function findNextFreeSequence(int $startSeq): array
    {
        $maxAttempts = 1000;
        $seq = $startSeq + 1;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = $this->formatSerialForSequence($seq);

            $exists = Estimate::where('company_id', $this->company_id)
                ->where('estimate_number', $candidate)
                ->where('id', '<>', $this->id)
                ->exists();

            if (! $exists) {
                return [$seq, $candidate];
            }

            $seq++;
        }

        throw new \RuntimeException(
            'No se encontró un número libre tras ' . $maxAttempts . ' intentos.'
        );
    }

    /**
     * Detecta si el número rellenado por el frontend corresponde a la
     * sugerencia automática (clean o skipped sobre huecos ocupados).
     * Ver Invoice::detectAutoSequenceNumber para detalles.
     *
     * @return int|null  sequence_number a asignar, o null si es manual.
     */
    protected function detectAutoSequenceNumber(string $candidateNumber, int $startSeq): ?int
    {
        $maxAttempts = 1000;
        $seq = $startSeq;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $formatted = $this->formatSerialForSequence($seq);

            if ($formatted === $candidateNumber) {
                if ($seq === $startSeq) {
                    return $seq;
                }
                for ($mid = $startSeq; $mid < $seq; $mid++) {
                    $midFormatted = $this->formatSerialForSequence($mid);
                    $occupied = Estimate::where('company_id', $this->company_id)
                        ->where('estimate_number', $midFormatted)
                        ->where('id', '<>', $this->id)
                        ->exists();
                    if (! $occupied) {
                        return null;
                    }
                }
                return $seq;
            }

            if (strcmp($formatted, $candidateNumber) > 0) {
                return null;
            }

            $seq++;
        }

        return null;
    }

    /**
     * Lock serializado por empresa. Ver Invoice::acquireNumberLock() para
     * explicación completa.
     */
    protected function acquireNumberLock(): void
    {
        $driver = DB::connection()->getDriverName();
        $companyId = (int) $this->company_id;

        if ($driver === 'pgsql') {
            // Onfactu — int32 signed safety:
            // PostgreSQL pg_advisory_xact_lock(int, int) requiere int32
            // con signo (max 2147483647). crc32() en PHP devuelve unsigned
            // int32 y puede superar ese límite (ej. 'estimates' = 2243473646).
            // Aplicamos AND con 0x7FFFFFFF para descartar el bit más alto
            // y garantizar un valor positivo dentro del rango int32.
            $tableKey = crc32('estimates') & 0x7FFFFFFF;
            DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [$tableKey, $companyId]);
        } elseif ($driver === 'mysql') {
            $lockName = "estimates_numbering_{$companyId}";
            DB::statement('SELECT GET_LOCK(?, 10)', [$lockName]);
        }
    }

    /**
     * Formatea un estimate_number para un sequence_number concreto.
     */
    protected function formatSerialForSequence(int $sequence): string
    {
        $format = CompanySetting::getSetting('estimate_number_format', $this->company_id)
            ?: '{{SERIES:EST}}{{DELIMITER:-}}{{SEQUENCE:6}}';

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

    public static function createItems($estimate, $request, $exchange_rate)
    {
        $estimateItems = $request->items;

        foreach ($estimateItems as $estimateItem) {
            $estimateItem['company_id'] = $request->header('company');
            $estimateItem['exchange_rate'] = $exchange_rate;
            $estimateItem['base_price'] = $estimateItem['price'] * $exchange_rate;
            $estimateItem['base_discount_val'] = $estimateItem['discount_val'] * $exchange_rate;
            $estimateItem['base_tax'] = $estimate['tax'] * $exchange_rate;
            $estimateItem['base_total'] = $estimateItem['total'] * $exchange_rate;

            $item = $estimate->items()->create($estimateItem);

            if (array_key_exists('taxes', $estimateItem) && $estimateItem['taxes']) {
                foreach ($estimateItem['taxes'] as $tax) {
                    if (gettype($tax['amount']) !== 'NULL') {
                        $tax['company_id'] = $request->header('company');
                        $item->taxes()->create($tax);
                    }
                }
            }

            if (array_key_exists('custom_fields', $estimateItem) && $estimateItem['custom_fields']) {
                $item->addCustomFields($estimateItem['custom_fields']);
            }
        }
    }

    public static function createTaxes($estimate, $request, $exchange_rate)
    {
        $estimateTaxes = $request->taxes;

        foreach ($estimateTaxes as $tax) {
            if (gettype($tax['amount']) !== 'NULL') {
                $tax['company_id'] = $request->header('company');
                $tax['exchange_rate'] = $exchange_rate;
                $tax['base_amount'] = $tax['amount'] * $exchange_rate;
                $tax['currency_id'] = $estimate->currency_id;

                $estimate->taxes()->create($tax);
            }
        }
    }

    public function sendEstimateData($data)
    {
        $data['estimate'] = $this->toArray();
        $data['user'] = $this->customer->toArray();
        $data['company'] = $this->company->toArray();
        $data['body'] = $this->getEmailBody($data['body']);
        $data['attach']['data'] = ($this->getEmailAttachmentSetting()) ? $this->getPDFData() : null;

        return $data;
    }

    public function send($data)
    {
        $data = $this->sendEstimateData($data);

        if ($this->status == Estimate::STATUS_DRAFT) {
            $this->status = Estimate::STATUS_SENT;
            $this->save();
        }

        \Mail::to($data['to'])->send(new SendEstimateMail($data));

        return [
            'success' => true,
            'type' => 'send',
        ];
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

        $estimateTemplate = self::find($this->id)->template_name;

        $company = Company::find($this->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);
        $customFields = CustomField::where('model_type', 'Item')->get();

        App::setLocale($locale);

        $logo = $company->logo_path;

        // Compartir $invoice como alias de $estimate para que invoice4.blade.php
        // (plantilla universal) funcione con todos los tipos de documento.
        // También se comparte $is_estimate para que la plantilla ajuste el título.
        view()->share([
            'estimate' => $this,
            'invoice' => $this,          // Alias para compatibilidad con invoice4
            'is_estimate' => true,       // Flag para que la plantilla muestre "PRESUPUESTO"
            'customFields' => $customFields,
            'logo' => $logo ?? null,
            'company_address' => $this->getCompanyAddress(),
            'shipping_address' => $this->getCustomerShippingAddress(),
            'billing_address' => $this->getCustomerBillingAddress(),
            'notes' => $this->getNotes(),
            'taxes' => $taxes,
        ]);

        // Buscar primero en estimate/; si no existe, buscar en invoice/ (fallback)
        $template = PdfTemplateUtils::findFormattedTemplate('estimate', $estimateTemplate, '');
        if ($template) {
            $templatePath = $template['custom'] ? sprintf('pdf_templates::estimate.%s', $estimateTemplate) : sprintf('app.pdf.estimate.%s', $estimateTemplate);
        } else {
            // Fallback: usar plantilla de invoice (invoice4 es universal)
            $template = PdfTemplateUtils::findFormattedTemplate('invoice', $estimateTemplate, '');
            $templatePath = $template['custom'] ? sprintf('pdf_templates::invoice.%s', $estimateTemplate) : sprintf('app.pdf.invoice.%s', $estimateTemplate);
        }

        if (request()->has('preview')) {
            return view($templatePath);
        }

        return PDF::loadView($templatePath);
    }

    public function getCompanyAddress()
    {
        if ($this->company && (! $this->company->address()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('estimate_company_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getCustomerShippingAddress()
    {
        if ($this->customer && (! $this->customer->shippingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('estimate_shipping_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getCustomerBillingAddress()
    {
        if ($this->customer && (! $this->customer->billingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('estimate_billing_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getNotes()
    {
        return $this->getFormattedString($this->notes);
    }

    public function getEmailAttachmentSetting()
    {
        $estimateAsAttachment = CompanySetting::getSetting('estimate_email_attachment', $this->company_id);

        if ($estimateAsAttachment == 'NO') {
            return false;
        }

        return true;
    }

    public function getEmailBody($body)
    {
        $values = array_merge($this->getFieldsArray(), $this->getExtraFields());

        $body = strtr($body, $values);

        return preg_replace('/{(.*?)}/', '', $body);
    }

    public function getExtraFields()
    {
        return [
            '{ESTIMATE_DATE}' => $this->formattedEstimateDate,
            '{ESTIMATE_EXPIRY_DATE}' => $this->formattedExpiryDate,
            '{ESTIMATE_NUMBER}' => $this->estimate_number,
            '{ESTIMATE_REF_NUMBER}' => $this->reference_number,
        ];
    }

    public function getInvoiceTemplateName()
    {
        $templateName = Str::replace('estimate', 'invoice', $this->template_name);

        $name = [];

        foreach (PdfTemplateUtils::getFormattedTemplates('invoice') as $template) {
            $name[] = $template['name'];
        }

        if (in_array($templateName, $name) == false) {
            $templateName = 'invoice1';
        }

        return $templateName;
    }

    public function checkForEstimateConvertAction()
    {
        $convertEstimateAction = CompanySetting::getSetting(
            'estimate_convert_action',
            $this->company_id
        );

        if ($convertEstimateAction === 'delete_estimate') {
            $this->delete();
        }

        if ($convertEstimateAction === 'mark_estimate_as_accepted') {
            $this->status = self::STATUS_ACCEPTED;
            $this->save();
        }

        return true;
    }
}
