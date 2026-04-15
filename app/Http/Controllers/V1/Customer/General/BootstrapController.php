<?php

namespace App\Http\Controllers\V1\Customer\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CustomerResource;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BootstrapController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $customer = Auth::guard('customer')->user();

        foreach (\Menu::get('customer_portal_menu')->items->toArray() as $data) {
                // Filtrar por option_key (opciones del plan)
                if (!empty($data->data['option_key']) && (int) app_cfg($data->data['option_key'], 0) !== 1) {
                    continue;
                }
            if ($customer) {
                $menu[] = [
                    'title' => $data->title,
                    'link' => $data->link->path['url'],
                ];
            }
        }

        // Opciones de menú deshabilitadas (para bloquear rutas en el frontend)
        $menuOptionKeys = [
            'OPCION_MENU_FACTURAS' => 'invoices',
            'OPCION_MENU_PRESUPUESTOS' => 'estimates',
            'OPCION_MENU_PROFORMAS' => 'proforma-invoices',
            'OPCION_MENU_ALBARANES' => 'delivery-notes',
            'OPCION_MENU_PAGOS' => 'payments',
            'OPCION_MENU_GASTOS' => 'expenses',
        ];
        $disabledMenuOptions = [];
        foreach ($menuOptionKeys as $configKey => $routeSegment) {
            if ((int) app_cfg($configKey, 1) !== 1) {
                $disabledMenuOptions[] = $routeSegment;
            }
        }

        return (new CustomerResource($customer))
            ->additional(['meta' => [
                'menu' => $menu,
                'current_customer_currency' => Currency::find($customer->currency_id),
                'modules' => Module::where('enabled', true)->pluck('name'),
                'current_company_language' => CompanySetting::getSetting('language', $customer->company_id),
                'disabled_menu_options' => $disabledMenuOptions,
            ]]);
    }
}
