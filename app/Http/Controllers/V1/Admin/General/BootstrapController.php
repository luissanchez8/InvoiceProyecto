<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Setting;
use App\Traits\GeneratesMenuTrait;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class BootstrapController extends Controller
{
    use GeneratesMenuTrait;

    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $current_user = $request->user();
        $current_user_settings = $current_user->getAllSettings();

        $main_menu = $this->generateMenu('main_menu', $current_user);

        $setting_menu = $this->generateMenu('setting_menu', $current_user);

        $companies = $current_user->companies;

        $current_company = Company::find($request->header('company'));

        if ((! $current_company) || ($current_company && ! $current_user->hasCompany($current_company->id))) {
            $current_company = $current_user->companies()->first();
        }

        $current_company_settings = CompanySetting::getAllSettings($current_company->id);

        $current_company_currency = $current_company_settings->has('currency')
            ? Currency::find($current_company_settings->get('currency'))
            : Currency::first();

        BouncerFacade::refreshFor($current_user);

        $global_settings = Setting::getSettings([
            'api_token',
            'admin_portal_theme',
            'admin_portal_logo',
            'login_page_logo',
            'login_page_heading',
            'login_page_description',
            'admin_page_title',
            'copyright_text',
        ]);

        // Opciones de menú deshabilitadas (para bloquear rutas en el frontend)
        $menuOptionKeys = [
            'OPCION_MENU_FACTURAS' => 'invoices',
            'OPCION_MENU_PRESUPUESTOS' => 'estimates',
            'OPCION_MENU_PROFORMAS' => 'proforma-invoices',
            'OPCION_MENU_ALBARANES' => 'delivery-notes',
            'OPCION_MENU_FRA_RECURRENTE' => 'recurring-invoices',
            'OPCION_MENU_PAGOS' => 'payments',
            'OPCION_MENU_GASTOS' => 'expenses',
        ];
        $disabledMenuOptions = [];
        foreach ($menuOptionKeys as $configKey => $routeSegment) {
            if ((int) app_cfg($configKey, 1) !== 1) {
                $disabledMenuOptions[] = $routeSegment;
            }
        }

        // Onfactu: VeriFactu sólo está "activo" si:
        //   1. La empresa tiene verifactu_enabled = 'YES' en CompanySettings.
        //   2. Y Asistencia ha puesto OPCION_VERIFACTU = 1 en app_config.
        // Si OPCION_VERIFACTU es 0 (o no existe), el usuario ve un botón
        // "Solicitar activación" en lugar del toggle.
        $opcionVerifactu = (int) app_cfg('OPCION_VERIFACTU', 0) === 1;

        return response()->json([
            'current_user' => new UserResource($current_user),
            'current_user_settings' => $current_user_settings,
            'current_user_abilities' => $current_user->getAbilities(),
            'companies' => CompanyResource::collection($companies),
            'current_company' => new CompanyResource($current_company),
            'current_company_settings' => $current_company_settings,
            'current_company_currency' => $current_company_currency,
            'config' => config('invoiceshelf'),
            'global_settings' => $global_settings,
            'main_menu' => $main_menu,
            'setting_menu' => $setting_menu,
            'modules' => Module::where('enabled', true)->pluck('name'),
            'disabled_menu_options' => $disabledMenuOptions,
            'opcion_verifactu' => $opcionVerifactu,
        ]);
    }
}
