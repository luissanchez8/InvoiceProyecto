{{--
|--------------------------------------------------------------------------
| invoice4.blade.php — Plantilla PDF profesional UNIVERSAL
|--------------------------------------------------------------------------
|
| Plantilla única para TODOS los tipos de documento:
|   - Factura (por defecto)
|   - Presupuesto ($is_estimate = true)
|   - Factura Proforma ($is_proforma = true)
|   - Albarán ($is_delivery_note = true)
|
| El diseño, estilo y estructura son IDÉNTICOS para todos los tipos.
| Solo cambia el título del documento y las etiquetas de número/fecha.
|
| Variables Blade disponibles:
|   - $invoice          : Modelo del documento (Invoice, Estimate, ProformaInvoice, DeliveryNote)
|   - $company_address  : HTML de la dirección de la empresa
|   - $billing_address  : HTML de la dirección de facturación
|   - $shipping_address : HTML de la dirección de envío
|   - $notes            : HTML de las notas
|   - $logo             : Ruta al logo de la empresa (o null)
|   - $taxes            : Colección de impuestos agrupados
|   - $customFields     : Campos personalizados del modelo Item
|   - $is_proforma      : (opcional) true si es factura proforma
|   - $is_delivery_note : (opcional) true si es albarán
|   - $is_estimate      : (opcional) true si es presupuesto
|   - $show_prices      : (opcional) false para ocultar precios en albaranes
|
--}}

{{-- ================================================================
     DETECCIÓN DEL TIPO DE DOCUMENTO
     Determina el título, etiquetas y número según el tipo.
     ================================================================ --}}
@php
    // Detectar tipo de documento por los flags compartidos
    $docType = 'invoice'; // Por defecto: factura
    if (!empty($is_estimate)) $docType = 'estimate';
    if (!empty($is_proforma)) $docType = 'proforma';
    if (!empty($is_delivery_note)) $docType = 'delivery_note';

    // Título grande del documento
    $docTitles = [
        'invoice'       => __('pdf_invoice_label'),
        'estimate'      => __('pdf_estimate_label'),
        'proforma'      => __('pdf_proforma_invoice_label'),
        'delivery_note' => __('pdf_delivery_note_label'),
    ];
    $docTitle = $docTitles[$docType] ?? __('pdf_invoice_label');

    // Número del documento (cada modelo usa un campo diferente)
    $docNumber = $invoice->invoice_number
        ?? $invoice->estimate_number
        ?? $invoice->proforma_invoice_number
        ?? $invoice->delivery_note_number
        ?? '';

    // Fecha principal del documento
    $docDate = $invoice->formattedInvoiceDate
        ?? $invoice->formattedEstimateDate
        ?? $invoice->formattedProformaInvoiceDate
        ?? $invoice->formattedDeliveryNoteDate
        ?? '';

    // Fecha secundaria (vencimiento, validez, entrega)
    $docSecondaryDate = $invoice->formattedDueDate
        ?? $invoice->formattedExpiryDate
        ?? $invoice->formattedDeliveryDate
        ?? '';

    // Etiqueta de la fecha secundaria
    $secondaryDateLabels = [
        'invoice'       => __('pdf_invoice_due_date_short'),
        'estimate'      => __('pdf_estimate_expire_date'),
        'proforma'      => __('pdf_proforma_invoice_expiry_date'),
        'delivery_note' => __('pdf_delivery_date'),
    ];
    $secondaryDateLabel = $secondaryDateLabels[$docType] ?? '';

    // Control de precios para albaranes (por defecto true)
    $showPrices = $show_prices ?? true;
@endphp

<!DOCTYPE html>
<html>

<head>
    <title>{{ $docTitle }} - {{ $docNumber }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style type="text/css">
        /* ============================================================
           FUENTES — Satoshi Regular y Black (negrita)
           ============================================================ */
        @font-face {
            font-family: 'Satoshi';
            font-style: normal;
            font-weight: normal;
            src: url("{{ resource_path('static/fonts/Satoshi-Regular.otf') }}") format('opentype');
        }
        @font-face {
            font-family: 'Satoshi';
            font-style: normal;
            font-weight: bold;
            src: url("{{ resource_path('static/fonts/Satoshi-Black.otf') }}") format('opentype');
        }

        /* ============================================================
           ESTILOS BASE
           ============================================================ */
        body {
            font-family: 'Satoshi', sans-serif !important;
            font-size: 12px;
            color: #333333;
            margin: 0;
            padding: 0;
        }
        html { margin: 0; padding: 0; }
        table { border-collapse: collapse; }

        /* ============================================================
           CABECERA — Logo izquierda + Título derecha
           ============================================================ */
        .header-table { width: 100%; padding: 30px 40px 20px 40px; }
        .header-logo-cell { vertical-align: top; width: 50%; }
        .header-logo-cell img { max-height: 60px; max-width: 200px; }
        .header-company-name { font-size: 24px; font-weight: bold; color: #333333; }
        .header-invoice-cell { vertical-align: top; text-align: right; width: 50%; }
        .header-invoice-title { font-size: 32px; font-weight: bold; color: #333333; margin: 0; padding: 0; line-height: 1.1; }
        .header-invoice-meta { font-size: 11px; color: #555555; line-height: 1.6; margin-top: 5px; }

        /* ============================================================
           DATOS DEL EMISOR
           ============================================================ */
        .issuer-section { padding: 15px 40px; border-bottom: 1px solid #dddddd; }
        .issuer-details { font-size: 11px; color: #555555; line-height: 1.5; }

        /* ============================================================
           CLIENTE + ENVÍO
           ============================================================ */
        .addresses-table { width: 100%; padding: 20px 40px 10px 40px; }
        .address-billing-cell { vertical-align: top; width: 50%; padding-right: 20px; }
        .address-shipping-cell { vertical-align: top; width: 50%; text-align: right; padding-left: 20px; }
        .address-label { font-size: 10px; font-style: italic; color: #888888; margin-bottom: 4px; }
        .address-detail { font-size: 11px; color: #555555; line-height: 1.5; }

        /* ============================================================
           TABLA DE CONCEPTOS
           ============================================================ */
        .items-section { padding: 15px 40px 0 40px; }
        .items-table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        .items-table thead th { background-color: #f5f5f5; font-size: 11px; font-weight: bold; color: #333333; padding: 8px 10px; border-top: 1px solid #dddddd; border-bottom: 1px solid #dddddd; }
        .items-table thead th.col-concept { text-align: left; width: 50%; }
        .items-table thead th.col-numeric { text-align: right; }
        .items-table tbody td { font-size: 11px; color: #333333; padding: 10px; border-bottom: 1px solid #eeeeee; vertical-align: top; }
        .items-table tbody td.col-concept { text-align: left; }
        .items-table tbody td.col-numeric { text-align: right; white-space: nowrap; }
        .item-description { color: #888888; font-size: 9px; line-height: 1.3; margin-top: 2px; }

        /* ============================================================
           BLOQUE DE TOTALES
           ============================================================ */
        .totals-section { padding: 5px 40px 0 40px; }
        .totals-table { float: right; width: auto; min-width: 280px; page-break-inside: avoid; }
        .totals-table td.total-label { font-size: 11px; color: #555555; text-align: right; padding: 4px 15px 4px 10px; }
        .totals-table td.total-value { font-size: 11px; color: #333333; text-align: right; padding: 4px 0 4px 10px; white-space: nowrap; }
        .totals-table td.total-final-label { font-size: 13px; font-weight: bold; color: #333333; text-align: right; padding: 8px 15px 8px 10px; border-top: 1px solid #dddddd; }
        .totals-table td.total-final-value { font-size: 15px; font-weight: bold; color: #333333; text-align: right; padding: 8px 0 8px 10px; border-top: 1px solid #dddddd; white-space: nowrap; }

        /* ============================================================
           FOOTER, FIRMA, PAGINACIÓN
           ============================================================ */
        .footer-content { clear: both; padding: 30px 40px 10px 40px; page-break-inside: avoid; }
        .footer-table { width: 100%; }
        .footer-notes-cell { vertical-align: top; width: 50%; padding-right: 15px; }
        .footer-terms-cell { vertical-align: top; width: 50%; padding-left: 15px; border-left: 1px solid #eeeeee; }
        .footer-section-title { font-size: 12px; font-weight: bold; color: #333333; margin-bottom: 5px; }
        .footer-section-text { font-size: 10px; color: #555555; line-height: 1.5; }
        .signature-section { padding: 20px 40px 10px 40px; page-break-inside: avoid; }
        .signature-label { font-size: 12px; font-weight: bold; color: #333333; margin-bottom: 40px; }
        .page-footer { padding: 10px 40px; text-align: right; font-size: 9px; color: #999999; }
        .clearfix { clear: both; }
    </style>

    @if (App::isLocale('th'))
        @include('app.pdf.locale.th')
    @endif
</head>

<body>

    {{-- ================================================================
         CABECERA: Logo + Título del documento + Metadatos
         ================================================================ --}}
    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                @if ($logo)
                    <img src="{{ \App\Space\ImageUtils::toBase64Src($logo) }}" alt="Logo">
                @else
                    <span class="header-company-name">{{ $invoice->company->name }}</span>
                @endif
            </td>

            {{-- Título dinámico según tipo de documento --}}
            <td class="header-invoice-cell">
                <div class="header-invoice-title">{{ $docTitle }}</div>
                <div class="header-invoice-meta">
                    @lang('pdf_invoice_number_short'): {{ $docNumber }}<br>
                    @lang('pdf_invoice_date_short'): {{ $docDate }}<br>
                    @if($docSecondaryDate)
                        {{ $secondaryDateLabel }}: {{ $docSecondaryDate }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- DATOS DEL EMISOR --}}
    <div class="issuer-section">
        <div class="issuer-details">
            {!! $company_address !!}
        </div>
    </div>

    {{-- DIRECCIONES: Cliente + Envío --}}
    <table class="addresses-table">
        <tr>
            <td class="address-billing-cell">
                @if ($billing_address)
                    <div class="address-label">@lang('pdf_invoice_customer_data')</div>
                    <div class="address-detail">{!! $billing_address !!}</div>
                @endif
            </td>
            <td class="address-shipping-cell">
                @if ($shipping_address)
                    <div class="address-label">@lang('pdf_invoice_shipping_address')</div>
                    <div class="address-detail">{!! $shipping_address !!}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ================================================================
         TABLA DE CONCEPTOS
         Si es albarán con show_prices=false, oculta columnas de precio/total
         ================================================================ --}}
    <div class="items-section">
        <table class="items-table" cellspacing="0">
            <thead>
                <tr>
                    <th class="col-concept">@lang('pdf_invoice_concept')</th>
                    @foreach($customFields as $field)
                        <th class="col-numeric">{{ $field->label }}</th>
                    @endforeach
                    <th class="col-numeric">@lang('pdf_quantity_label')</th>
                    {{-- Precio y total solo si showPrices es true --}}
                    @if($showPrices)
                        <th class="col-numeric">@lang('pdf_price_label')</th>
                        @if($invoice->discount_per_item === 'YES')
                            <th class="col-numeric">@lang('pdf_discount_label')</th>
                        @endif
                        @if($invoice->tax_per_item === 'YES')
                            <th class="col-numeric">@lang('pdf_tax_label')</th>
                        @endif
                        <th class="col-numeric">@lang('pdf_total')</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td class="col-concept">
                            <span>{{ $item->name }}</span>
                            @if($item->description)
                                <div class="item-description">{!! nl2br(htmlspecialchars($item->description)) !!}</div>
                            @endif
                        </td>
                        @foreach($customFields as $field)
                            <td class="col-numeric">{{ $item->getCustomFieldValueBySlug($field->slug) }}</td>
                        @endforeach
                        <td class="col-numeric">{{ $item->quantity }}@if($item->unit_name) {{ $item->unit_name }}@endif</td>
                        @if($showPrices)
                            <td class="col-numeric">{!! format_money_pdf($item->price, $invoice->customer->currency) !!}</td>
                            @if($invoice->discount_per_item === 'YES')
                                <td class="col-numeric">
                                    @if($item->discount_type === 'fixed')
                                        {!! format_money_pdf($item->discount_val, $invoice->customer->currency) !!}
                                    @elseif($item->discount_type === 'percentage')
                                        {{ $item->discount }}%
                                    @endif
                                </td>
                            @endif
                            @if($invoice->tax_per_item === 'YES')
                                <td class="col-numeric">{!! format_money_pdf($item->tax, $invoice->customer->currency) !!}</td>
                            @endif
                            <td class="col-numeric">{!! format_money_pdf($item->total, $invoice->customer->currency) !!}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ================================================================
         BLOQUE DE TOTALES (solo si showPrices es true)
         ================================================================ --}}
    @if($showPrices)
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="total-label">@lang('pdf_subtotal'):</td>
                    <td class="total-value">{!! format_money_pdf($invoice->sub_total, $invoice->customer->currency) !!}</td>
                </tr>

                @if($invoice->discount > 0 && $invoice->discount_per_item === 'NO')
                    <tr>
                        <td class="total-label">
                            @lang('pdf_discount_label')@if($invoice->discount_type === 'percentage') ({{ $invoice->discount }}%)@endif:
                        </td>
                        <td class="total-value">-{!! format_money_pdf($invoice->discount_val, $invoice->customer->currency) !!}</td>
                    </tr>
                    <tr>
                        <td class="total-label">@lang('pdf_invoice_base_amount'):</td>
                        <td class="total-value">{!! format_money_pdf($invoice->sub_total - $invoice->discount_val, $invoice->customer->currency) !!}</td>
                    </tr>
                @endif

                @if ($invoice->tax_per_item === 'YES')
                    @foreach ($taxes as $tax)
                        <tr>
                            <td class="total-label">
                                @if($tax->calculation_type === 'fixed')
                                    {{ $tax->name }} ({!! format_money_pdf($tax->fixed_amount, $invoice->customer->currency) !!}):
                                @else
                                    {{ $tax->name }} ({{ $tax->percent }}%) :
                                @endif
                            </td>
                            <td class="total-value">{!! format_money_pdf($tax->amount, $invoice->customer->currency) !!}</td>
                        </tr>
                    @endforeach
                @else
                    @foreach ($invoice->taxes as $tax)
                        <tr>
                            <td class="total-label">
                                @if($tax->calculation_type === 'fixed')
                                    {{ $tax->name }} ({!! format_money_pdf($tax->fixed_amount, $invoice->customer->currency) !!}):
                                @else
                                    {{ $tax->name }} ({{ $tax->percent }}%) :
                                @endif
                            </td>
                            <td class="total-value">{!! format_money_pdf($tax->amount, $invoice->customer->currency) !!}</td>
                        </tr>
                    @endforeach
                @endif

                <tr>
                    <td class="total-final-label">@lang('pdf_invoice_total_to_pay'):</td>
                    <td class="total-final-value">{!! format_money_pdf($invoice->total, $invoice->customer->currency) !!}</td>
                </tr>

                {{-- Pagado/pendiente solo para facturas reales --}}
                @if(isset($invoice->paid_status) && ($invoice->paid_status === 'PARTIALLY_PAID' || $invoice->paid_status === 'PAID'))
                    <tr>
                        <td class="total-label">@lang('pdf_amount_paid'):</td>
                        <td class="total-value">{!! format_money_pdf($invoice->total - $invoice->due_amount, $invoice->customer->currency) !!}</td>
                    </tr>
                    <tr>
                        <td class="total-final-label">@lang('pdf_amount_due'):</td>
                        <td class="total-final-value">{!! format_money_pdf($invoice->due_amount, $invoice->customer->currency) !!}</td>
                    </tr>
                @endif
            </table>
        </div>
        <div class="clearfix"></div>
    @endif

    {{-- NOTAS --}}
    @if ($notes)
        <div class="footer-content">
            <table class="footer-table">
                <tr>
                    <td class="footer-notes-cell">
                        <div class="footer-section-title">@lang('pdf_notes')</div>
                        <div class="footer-section-text">{!! $notes !!}</div>
                    </td>
                    <td class="footer-terms-cell"></td>
                </tr>
            </table>
        </div>
    @endif

    {{-- FIRMA --}}
    <div class="signature-section">
        <div class="signature-label">@lang('pdf_invoice_signature')</div>
    </div>

    {{-- PAGINACIÓN --}}
    <div class="page-footer">
        @lang('pdf_invoice_page') 1 @lang('pdf_invoice_of') 1
    </div>

</body>
</html>
