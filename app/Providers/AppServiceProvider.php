<?php
namespace App\Providers;
use App\Bouncer\Scopes\DefaultScope;
use App\Helpers\AppConfig;
use App\Models\Company;
use App\Models\Address;
use App\Policies\AddressPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\EstimatePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ItemPolicy;
use App\Policies\ModulesPolicy;
use App\Policies\NotePolicy;
use App\Policies\OwnerPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RecurringInvoicePolicy;
use App\Policies\ReportPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingsPolicy;
use App\Policies\ProformaInvoicePolicy;
use App\Policies\DeliveryNotePolicy;
use App\Policies\UserPolicy;
use App\Space\InstallUtils;
use Gate;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Silber\Bouncer\Database\Models as BouncerModels;
use Silber\Bouncer\Database\Role;
use View;
class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/admin/dashboard';
    public const CUSTOMER_HOME = '/customer/dashboard';

    public function boot(): void
    {
        if (InstallUtils::isDbCreated()) {
            $this->addMenus();
        }
        Gate::policy(Role::class, RolePolicy::class);
        View::addNamespace('pdf_templates', storage_path('app/templates/pdf'));
        View::share('appConfig', AppConfig::load());
        $this->bootAuth();
        $this->bootBroadcast();
        if (config('app.env') === 'demo') {
            \Illuminate\Support\Facades\Mail::fake();
            \Illuminate\Support\Facades\Notification::fake();
        }
        try {
            $this->app->booted(function () {
                $mailer = app('mail.manager')->mailer();
                $transport = $mailer->getSymfonyTransport();
                if ($transport instanceof \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport) {
                    $transport->getStream()->setStreamOptions([
                        'ssl' => [
                            'allow_self_signed' => true,
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                    ]);
                }
            });
        } catch (\Throwable $e) {}
    }

    public function register(): void
    {
        BouncerModels::scope(new DefaultScope);
    }

    public function addMenus()
    {
        \Menu::make('main_menu', function ($menu) {
            foreach (config('invoiceshelf.main_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
        \Menu::make('setting_menu', function ($menu) {
            foreach (config('invoiceshelf.setting_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
        \Menu::make('customer_portal_menu', function ($menu) {
            foreach (config('invoiceshelf.customer_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
    }

    public function generateMenu($menu, $data)
    {
        if (!empty($data['option_key']) && (int) app_cfg($data['option_key'], 0) !== 1) {
            return;
        }
        $item = $menu->add($data['title'], $data['link'])
            ->data('icon', $data['icon'])
            ->data('name', $data['name'])
            ->data('owner_only', $data['owner_only'])
            ->data('ability', $data['ability'])
            ->data('model', $data['model'])
            ->data('group', $data['group'])
            ->data('asistencia_only', $data['asistencia_only'] ?? false);
        if (!empty($data['option_key'])) {
            $item->data('option_key', $data['option_key']);
        }
        if (!empty($data['custom_icon'])) {
            $item->data('custom_icon', $data['custom_icon']);
        }
        if (!empty($data['custom_icon_active'])) {
            $item->data('custom_icon_active', $data['custom_icon_active']);
        }
        if (!empty($data['external'])) {
            $item->data('external', true);
        }
        if (!empty($data['action'])) {
            $item->data('action', $data['action']);
        }
    }

    public function bootAuth()
    {
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(\App\Models\ProformaInvoice::class, ProformaInvoicePolicy::class);
        Gate::policy(\App\Models\DeliveryNote::class, DeliveryNotePolicy::class);
        Gate::define('create company', [CompanyPolicy::class, 'create']);
        Gate::define('transfer company ownership', [CompanyPolicy::class, 'transferOwnership']);
        Gate::define('delete company', [CompanyPolicy::class, 'delete']);
        Gate::define('manage modules', [ModulesPolicy::class, 'manageModules']);
        Gate::define('manage settings', [SettingsPolicy::class, 'manageSettings']);
        Gate::define('manage company', [SettingsPolicy::class, 'manageCompany']);
        Gate::define('manage backups', [SettingsPolicy::class, 'manageBackups']);
        Gate::define('manage file disk', [SettingsPolicy::class, 'manageFileDisk']);
        Gate::define('manage email config', [SettingsPolicy::class, 'manageEmailConfig']);
        Gate::define('manage pdf config', [SettingsPolicy::class, 'managePDFConfig']);
        Gate::define('manage notes', [NotePolicy::class, 'manageNotes']);
        Gate::define('view notes', [NotePolicy::class, 'viewNotes']);
        Gate::define('send invoice', [InvoicePolicy::class, 'send']);
        Gate::define('send estimate', [EstimatePolicy::class, 'send']);
        Gate::define('send payment', [PaymentPolicy::class, 'send']);
        Gate::define('delete multiple items', [ItemPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple customers', [CustomerPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple users', [UserPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple invoices', [InvoicePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple estimates', [EstimatePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple expenses', [ExpensePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple payments', [PaymentPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple recurring invoices', [RecurringInvoicePolicy::class, 'deleteMultiple']);
        Gate::define('send proforma invoice', [ProformaInvoicePolicy::class, 'send']);
        Gate::define('delete multiple proforma invoices', [ProformaInvoicePolicy::class, 'deleteMultiple']);
        Gate::define('send delivery note', [DeliveryNotePolicy::class, 'send']);
        Gate::define('delete multiple delivery notes', [DeliveryNotePolicy::class, 'deleteMultiple']);
        Gate::define('view dashboard', [DashboardPolicy::class, 'view']);
        Gate::define('view report', [ReportPolicy::class, 'viewReport']);
        Gate::define('owner only', [OwnerPolicy::class, 'managedByOwner']);
    }

    public function bootBroadcast()
    {
        Broadcast::routes(['middleware' => 'api.auth']);
    }
}
