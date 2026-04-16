# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

InvoiceShelf is an open-source invoicing web application (fork of [Crater](https://crater.finance)) built with **Laravel 12** (PHP 8.2+) backend and **Vue 3** SPA frontend. It manages invoices, estimates, expenses, payments, customers, recurring invoices, and supports multi-company tenancy with role-based access control.

## Commands

### Development

```bash
# Start all dev services concurrently (server + queue + logs + vite)
composer dev

# Frontend only
npm run dev       # Vite dev server
npm run build     # Production build
```

### Testing

```bash
# All tests (Pest on SQLite :memory:)
php artisan test

# Single file
./vendor/bin/pest tests/Feature/Admin/InvoiceTest.php

# Single test by name
./vendor/bin/pest --filter="test_name_here"

# Via Makefile
make test
```

Tests use `RefreshDatabase` and seed with `DatabaseSeeder` + `DemoSeeder` in `beforeEach`. Default test user: `admin@invoiceshelf.com` / `invoiceshelf@123`. Demo user: `demo@invoiceshelf.com` / `demo`.

### Linting & Formatting

```bash
# PHP code style (Laravel Pint)
./vendor/bin/pint          # Fix
./vendor/bin/pint --test   # Check only (CI uses this)

# JS/Vue linting (ESLint + Prettier)
npm test
```

**Code style conventions:**
- PHP: Laravel Pint (PSR-12 based)
- JS/Vue: ESLint vue3-recommended + Prettier (no semicolons, single quotes, 2-space indent)
- PHP files: 4-space indent. Vue/JS/JSON/YAML: 2-space indent

### Docker Dev Environment

```bash
docker compose -f .dev/docker-compose.mysql.yml up --build    # MySQL
docker compose -f .dev/docker-compose.pgsql.yml up --build    # PostgreSQL
docker compose -f .dev/docker-compose.sqlite.yml up --build   # SQLite

# SSH into container
docker exec -it --user invoiceshelf invoiceshelf-dev-php /bin/bash
```

Requires hosts entry `127.0.0.1 invoiceshelf.test`. Services: Nginx (:80), Adminer (:8080), Mailpit (:8025), Vite (:5173), Gotenberg (internal PDF).

### Custom Artisan Commands

```bash
php artisan check:invoices:status    # Mark overdue invoices
php artisan check:estimates:status   # Mark expired estimates
```

## Architecture

### Multi-Tenancy Model

The app is **multi-tenant via Company**. Every API request includes a `company` header (set by `CompanyMiddleware`). Bouncer permissions are scoped per company via `ScopeBouncer` middleware. The middleware chain for authenticated API routes is: `auth:sanctum` → `company` → `bouncer`.

Users belong to multiple companies via `user_company` pivot table. Each company has its own settings (`company_settings`), roles, customers, invoices, etc. The company owner has full access; other users get permissions via Bouncer roles.

### Backend (Laravel)

**Request flow:** Routes → Middleware → Controller → Model (static factory methods) → Response

**Routes:**
- `routes/api.php` — REST API under `/api/v1/`. Grouped by domain. Sanctum auth + company + bouncer middleware.
- `routes/web.php` — Auth, PDF endpoints, reports, catch-all routes that serve the Vue SPA via `app.blade.php`.

**Controllers** (`app/Http/Controllers/V1/`):
- `Admin/` — Grouped by domain: Invoice/, Estimate/, Payment/, Expense/, Customer/, Item/, RecurringInvoice/, ProformaInvoice/, DeliveryNote/, Settings/, Company/, Role/, Dashboard/, Report/, ExchangeRate/, General/, Backup/, Modules/, Update/, Users/
- `Customer/` — Customer portal: read-only access to invoices, estimates, payments, profile
- `Installation/` — Setup wizard (database config, requirements check, domain setup)
- `PDF/` — PDF download endpoints for invoices, estimates, payments
- `Webhook/` — Cron job endpoint for recurring invoice generation

**Models** (`app/Models/`) — Core domain models with static factory methods:

| Model | Key Relationships | Static Methods |
|-------|------------------|----------------|
| **Company** | hasMany(Customer, Invoice, Estimate, Payment, Expense, RecurringInvoice), belongsTo(User, 'owner_id'), belongsToMany(User) | `setupDefaultData()`, `setupRoles()`, `deleteCompany()` |
| **Invoice** | hasMany(InvoiceItem, Tax, Payment, Transaction), morphMany(EmailLog), belongsTo(Customer, Company, Currency) | `createInvoice()`, `updateInvoice()`, `deleteInvoices()` |
| **Estimate** | hasMany(EstimateItem, Tax), morphMany(EmailLog), belongsTo(Customer, Company, Currency) | `createEstimate()`, `updateEstimate()`, `deleteEstimates()` |
| **Payment** | belongsTo(Invoice, Customer, Company, PaymentMethod, Transaction) | `createPayment()`, `updatePayment()`, `deletePayments()` |
| **Customer** | hasMany(Invoice, Estimate, Payment, Expense, Address, RecurringInvoice), belongsTo(Company, Currency) | `createCustomer()`, `updateCustomer()`, `deleteCustomers()` |
| **Expense** | belongsTo(ExpenseCategory, Customer, Company, PaymentMethod, Currency) | `createExpense()`, `updateExpense()` |
| **RecurringInvoice** | hasMany(Invoice, InvoiceItem, Tax), belongsTo(Customer, Company, Currency) | `generateInvoice()`, `getNextInvoiceDate()` |
| **User** | belongsToMany(Company), hasMany(Invoice, Estimate, Payment via creator_id) | `createFromRequest()`, `updateFromRequest()` |
| **ProformaInvoice** | hasMany(ProformaInvoiceItem, Tax), morphMany(EmailLog), belongsTo(Customer, Company, Currency, Invoice via converted_invoice_id) | `createProformaInvoice()`, `updateProformaInvoice()`, `convertToInvoice()`, `deleteProformaInvoices()` |
| **DeliveryNote** | hasMany(DeliveryNoteItem, Tax), morphMany(EmailLog), belongsTo(Customer, Company, Currency). Has `show_prices` boolean. | `createDeliveryNote()`, `updateDeliveryNote()`, `deleteDeliveryNotes()` |

**Document statuses:**
- **Invoice:** DRAFT → SENT → VIEWED → COMPLETED. Paid: UNPAID → PARTIALLY_PAID → PAID
- **Estimate:** DRAFT → SENT → VIEWED → ACCEPTED / REJECTED / EXPIRED
- **ProformaInvoice:** DRAFT → SENT → VIEWED → ACCEPTED / REJECTED (convertible to Invoice)
- **DeliveryNote:** DRAFT → SENT → DELIVERED

**Key services:**
- `SerialNumberFormatter` — Generates document numbers from format templates with placeholders: `{{SERIES:INV}}{{DELIMITER:-}}{{SEQUENCE:6}}` → "INV-000001". The setting key is `{modelname_lowercase}_number_format` (e.g., `proformainvoice_number_format` for ProformaInvoice — note: no underscores between words, derived from `strtolower(class_basename(Model))`). `NextNumberController` handles keys `invoice`, `estimate`, `payment`, `proforma_invoice`/`proformainvoice`, `delivery_note`/`deliverynote`.
- `GeneratesPdfTrait` — PDF generation with DomPDF or Gotenberg. Templates stored in `storage/app/templates/pdf`. Variable substitution for customer/company/address/custom fields.

**Universal PDF template (`invoice4.blade.php`):** Single template for ALL document types (Invoice, Estimate, ProformaInvoice, DeliveryNote). Detects type via flags `$is_estimate`, `$is_proforma`, `$is_delivery_note` and adapts title/labels accordingly. All models share `$invoice` as variable name for compatibility. DeliveryNote additionally passes `$show_prices` (boolean) to hide price columns. Estimate model's `getPDFData()` shares `$invoice` as alias for `$estimate` and falls back to `invoice/` templates if no `estimate/` template exists.

**PDF web routes:** Each document type has a public PDF route for iframe preview:
- `/invoices/pdf/{invoice:unique_hash}` → `InvoicePdfController`
- `/estimates/pdf/{estimate:unique_hash}` → `EstimatePdfController`
- `/proforma-invoices/pdf/{proformaInvoice:unique_hash}` → `ProformaInvoicePdfController`
- `/delivery-notes/pdf/{deliveryNote:unique_hash}` → `DeliveryNotePdfController`

**Traits:**
- `HasCustomFieldsTrait` — Polymorphic custom fields (morphMany to CustomFieldValue). Types: Input, TextArea, Phone, Url, Select, Checkbox, Number, Date, DateTime, Time.
- `GeneratesPdfTrait` — PDF rendering + storage (local/S3/Dropbox).

**Authorization** — Silber/Bouncer with abilities defined in `config/abilities.php`. Abilities have dependencies (e.g., `create-invoice` depends on `view-item`, `view-tax-type`, `view-customer`, `view-custom-field`, `view-all-notes`). Policies in `app/Policies/` enforce via Gate definitions in `AppServiceProvider`.

**CRITICAL: Bouncer scope** — All abilities are scoped per company via `ScopeBouncer` middleware. When creating new abilities programmatically, you MUST set the Bouncer scope first: `Bouncer::scope()->to($companyId)` before calling `Bouncer::allow($user)->to('ability-name')`. Abilities created without scope (scope=NULL) will NOT be found by the middleware and will cause 404/403 errors in the frontend. Existing abilities have `scope` = company_id in the `abilities` DB table.

**Global helpers:**
- `app/Space/helpers.php` — `get_company_setting()`, `get_app_setting()`, `get_page_title()`, `clean_slug()`
- `app/Support/appcfg.php` — `app_cfg($key, $default)` reads from `app_config` DB table with request-level memoization. Safe fallback on missing DB.
- `app/Helpers/AppConfig.php` — Reads `appConfig.cfg` file (key=value format) for deployment-level config like `URL_LOGOTIPO`.

**Jobs:** `GenerateInvoicePdfJob`, `GenerateEstimatePdfJob`, `GeneratePaymentPdfJob` (auto-dispatched on model events), `CreateBackupJob`.

**Menu toggle system (`app_config` table):** Menu items in `config/invoiceshelf.php` can have an `option_key` field. `AppServiceProvider::generateMenu()` checks `app_cfg($option_key, 0)` and skips items where the value is not `1`. Current toggles: `OPCION_MENU_PRESUPUESTOS`, `OPCION_MENU_FACTURAS`, `OPCION_MENU_FRA_RECURRENTE`, `OPCION_MENU_PAGOS`, `OPCION_MENU_GASTOS`, `OPCION_MENU_PROFORMA`, `OPCION_MENU_ALBARANES`.

**Mail:** `SendInvoiceMail`, `SendEstimateMail`, `SendPaymentMail`, `InvoiceViewedMail`, `EstimateViewedMail`. Each creates an `EmailLog` entry with unique token for tracking.

**Middleware stack:**
- `CompanyMiddleware` — Sets company from header; falls back to user's first company
- `ScopeBouncer` — Scopes Bouncer permissions to current company ID
- `CustomerPortalMiddleware` — Validates customer portal is enabled
- `CronJobMiddleware` — Validates `x-authorization-token` header for cron endpoints
- `RedirectIfInstalled` / `InstallationMiddleware` — Controls installation wizard flow

### Frontend (Vue 3 SPA)

**Bootstrap chain:** `main.js` → imports SCSS + axios plugin → creates `InvoiceShelf` instance → `app.blade.php` calls `InvoiceShelf.start()` → creates Vue app with Pinia, vue-router, vue-i18n, Vuelidate → mounts to `<body>`.

**Key libraries:** Vue 3, Pinia (state), vue-router 4, vue-i18n, Vuelidate (validation), Axios, Chart.js, TipTap (rich text editor), Moment.js, Lodash, Headless UI, Heroicons, Flatpickr (dates), v-money3, Maska (input masks).

**Vite aliases:** `@` → `resources/`, `$fonts` → `resources/static/fonts`, `$images` → `resources/static/img`.

**Two-portal SPA architecture:**

1. **Admin portal** (`/admin/*`) — Full management interface
   - Router: `resources/scripts/admin/admin-router.js`
   - Views: `resources/scripts/admin/views/` — Organized by domain (invoices/, estimates/, payments/, expenses/, customers/, items/, users/, settings/, reports/, modules/, recurring-invoices/, proforma-invoices/, delivery-notes/, installation/)
   - Stores: `resources/scripts/admin/stores/` — One Pinia store per domain (invoice.js, estimate.js, payment.js, customer.js, expense.js, item.js, company.js, user.js, global.js, auth.js, dashboard.js, tax-type.js, note.js, role.js, custom-field.js, recurring-invoice.js, proforma-invoice.js, delivery-note.js, exchange-rate.js, category.js, backup.js, module.js, mail-driver.js, pdf-driver.js, disk.js, installation.js, reset.js)
   - Components: `resources/scripts/admin/components/` — 60+ modal components in `modal-components/`, action dropdowns in `dropdowns/`, shared invoice/estimate components in `estimate-invoice-common/`, custom field editors in `custom-fields/`

2. **Customer portal** (`/{company:slug}/customer/*`) — Read-only portal
   - Router: `resources/scripts/customer/customer-router.js`
   - Views: `resources/scripts/customer/views/` — dashboard, invoices, estimates, payments, settings/profile
   - Stores: `resources/scripts/customer/stores/` — Separate auth, global, user, invoice, estimate, payment, dashboard, customer stores

**Shared components** (`resources/scripts/components/`):
- `base/` — 70+ reusable components: BaseButton, BaseInput, BaseTable (with pagination), BaseModal, BaseDialog, BaseDatePicker, BaseEditor (TipTap), BaseMoney, BaseBadge, status badges (Invoice/Estimate/Payment), BaseWizard, BaseDropdown, BaseTab, skeleton loaders, etc.
- `base-select/` — Custom multiselect dropdown with composables
- `CompanySwitcher.vue`, `GlobalSearchBar.vue`

**Global stores** (`resources/scripts/stores/`): `notification.js` (toasts), `dialog.js` (confirmation dialogs with Promises), `modal.js` (dynamic modal management).

**Data stubs** (`resources/scripts/admin/stub/`): Default data shapes for new entities — used to initialize store state for create forms.

**Axios configuration** (`resources/scripts/plugins/axios.js`): Sets credentials, CSRF token, auth token from localStorage, company header from localStorage (`selectedCompany`).

**Route guards:** Routes have meta properties `requiresAuth`, `ability` (Bouncer ability string), `isOwner` (owner-only). `router.beforeEach` checks these via `userStore.hasAbilities()` and `userStore.currentUser.is_owner`.

**Patterns used in views (IMPORTANT for new views):**
- **List pages (Index):** BaseTable requires `:data="fetchData"` where `fetchData` is an **async function** receiving `{ page, filter, sort }` and returning `{ data, pagination: { totalPages, currentPage, totalCount, limit } }`. Row data accessed via `row.data.field` (not `row.field`). Controllers must return Laravel's paginator directly (not wrapped in extra `{ data: paginator }`) so the response includes `data[]`, `last_page`, `total`, etc.
- **Create/Edit pages:** Detect edit mode from route params → fetch + populate store state → Vuelidate validation → submit via store action → notification on success/error
- **View/Detail pages:** Fixed sidebar left with scrollable list of documents (search, sort, scroll-to-active, infinite scroll via scroll listener). Main area: PageHeader with action buttons + iframe loading PDF via `/{type}/pdf/{unique_hash}?preview`. The sidebar fetches all documents via the store's fetch action and renders router-links.
- **BaseCustomerSelectPopup:** Hardcodes store references per `type` prop. Supported types: `estimate`, `invoice`, `proforma-invoice`, `delivery-note`, `recurring-invoice`. Each type must have `selectCustomer(id)` and `resetSelectedCustomer()` in its store. When adding new document types, this component MUST be updated.

**Module extensibility:** `InvoiceShelf.js` exposes `booting(callback)` for modules to register routes and components, and `addMessages(moduleMessages)` for i18n. Module scripts/styles loaded dynamically in `app.blade.php`.

### Styling & Theming

**Tailwind CSS 3** with plugins: @tailwindcss/forms, typography, aspect-ratio, scrollbar, ios-full-height.

**Theme system:** CSS custom properties `--color-primary-50` through `--color-primary-950` defined in `resources/sass/themes.scss`. Body gets `theme-{name}` class. Tailwind config references these via `withOpacityValue()` helper for opacity support.

**Font:** Satoshi (custom, loaded via @font-face in `invoiceshelf.scss`).

**Blade template:** Single `resources/views/app.blade.php` serves both portals. Sets global window vars (`customer_logo`, `brand_name`, `LOGO_ENDPOINT`), loads module assets, calls `InvoiceShelf.start()`.

### Database

**131 migrations.** Key tables: companies, users, customers, invoices, invoice_items, estimates, estimate_items, payments, expenses, recurring_invoices, proforma_invoices, proforma_invoice_items, delivery_notes, delivery_note_items, transactions, taxes, tax_types, items, units, currencies, payment_methods, expense_categories, custom_fields, custom_field_values, addresses, company_settings, user_settings, settings, email_logs, exchange_rate_logs, exchange_rate_providers, file_disks, modules, notes, app_config, media (Spatie), bouncer tables (roles, abilities, permissions).

**25 factories** available for all core models. Key factory states on InvoiceFactory: `sent`, `viewed`, `completed`, `unpaid`, `partially_paid`, `paid`.

**Seeders:** `DatabaseSeeder` (currencies, countries, users), `DemoSeeder` (demo company, 5 customers, settings, installation marker).

### Testing

- **Pest v3** with Laravel plugin. `tests/Pest.php` applies `RefreshDatabase` globally.
- **TestCase** uses `AdditionalAssertions` trait (from `jasonmccreary/laravel-test-assertions`) and custom factory namespace resolution.
- **Feature tests:** `tests/Feature/Admin/` (19 test files) and `tests/Feature/Customer/` (6 test files).
- **Test pattern:** `beforeEach` seeds DB → creates user with Sanctum token → sets company header → tests CRUD + business logic + mail assertions.
- **PHPUnit config** (`phpunit.xml`): SQLite :memory:, array cache/mail/session/queue.

### CI (GitHub Actions)

`.github/workflows/check.yaml`:
1. PHP code style check with Pint (`./vendor/bin/pint --test`)
2. Tests on PHP 8.2, 8.3, 8.4 (requires Node 20 for frontend build)
3. Release zip build on tags via `make clean dist`

### i18n

30+ language files in `lang/*.json`. Managed via Crowdin. Frontend uses vue-i18n with `$t()` global injection. Module messages merged via `InvoiceShelf.addMessages()`.

**Translation key conventions:** Use `general.*` for keys shared across document types (e.g., `general.mark_as_sent`). Use `{doctype}s.*` for document-specific keys (e.g., `invoices.mark_as_sent`). When adding UI text, always check if the key already exists before adding a new one. Custom keys added for this project include `pdf_invoice_*`, `pdf_proforma_invoice_*`, `pdf_delivery_note_*`, `navigation.*`, `settings.customization.*`.

### Key Config Files

- `config/invoiceshelf.php` — App-specific: min versions, supported languages, fiscal years, estimate conversion actions, retrospective edit policies, menu definitions (main_menu, setting_menu, customer_menu with ability/model/group/option_key)
- `config/abilities.php` — Bouncer RBAC abilities with dependency chains
- `config/modules.php` — Module system (nwidart/laravel-modules): paths, generators, activator
- `config/pdf.php` — PDF driver config (gotenberg or dompdf)
- `config/installer.php` — PHP 8.2 requirement, required extensions (exif, pdo, bcmath, openssl, mbstring, json, xml, fileinfo, zip, curl, sqlite3), directory permissions
- `config/database.php` — Includes extra `stripe` connection to query users_onfactu_stripe DB for plan info

## Onfactu-specific customizations

This is a fork of InvoiceShelf deployed as instances in Onfactu Pro. Each instance runs in Docker with its own PostgreSQL database. Several customizations have been added on top of the base InvoiceShelf:

### Proforma Invoices & Delivery Notes (MEJORAS.md)

New document types added: `proforma_invoices` and `delivery_notes` tables with their own controllers, models, resources, views, PDF templates, and both admin and customer portal support. Each follows the same patterns as `Invoice`/`Estimate`.

### Menu Options Toggle System (`app_config` table)

The `app_config` DB table stores instance-level settings. Menu items in `config/invoiceshelf.php` use `option_key` to toggle visibility based on the user's plan. Helper: `app_cfg($key, $default)` in `app/Support/appcfg.php`.

Current toggles: `OPCION_MENU_FACTURAS`, `OPCION_MENU_PRESUPUESTOS`, `OPCION_MENU_PROFORMAS`, `OPCION_MENU_ALBARANES`, `OPCION_MENU_FRA_RECURRENTE`, `OPCION_MENU_PAGOS`, `OPCION_MENU_GASTOS`.

### Asistencia Panel

A special user role `asistencia` (email: `asistencia@onfactu.com`) has access to **Settings → App Config** where it can toggle menu options, edit config values, and query the contracted plan from the Stripe DB in real time. Endpoints:

- `GET /api/v1/app-config` — List all config
- `PUT /api/v1/app-config` — Update configs
- `GET /api/v1/app-config/plan-from-stripe` — Query Stripe DB by admin email

See `app/Http/Controllers/V1/Admin/Settings/AppConfigController.php` and `resources/scripts/admin/views/settings/AppConfigSetting.vue`.

### Multi-layer Security for Disabled Routes

When a menu option is disabled in `app_config`, access is blocked at 4 layers:
1. **Sidebar admin** — `AppServiceProvider::generateMenu()` filters by `option_key`
2. **Customer portal menu** — `CustomerBootstrapController` filters by `option_key`
3. **Frontend router** — `router.beforeEach` redirects to dashboard if path contains a disabled segment
4. **Backend API** — `CheckMenuOption` middleware returns 403 (applied to `bouncer` and `auth:customer` groups)

The frontend gets `disabled_menu_options: string[]` from bootstrap endpoints and stores them in `globalStore.disabledMenuOptions`.

### Customer View with Tabs (admin)

`/admin/customers/:id/view` shows tabs: Dashboard (chart) + Invoices + Estimates + Payments + Proforma Invoices + Delivery Notes + Expenses. Each tab filters by `customer_id`. Tabs respect `disabledMenuOptions`. See `resources/scripts/admin/views/customers/View.vue` + `partials/CustomerDocumentsTab.vue`.

### VeriFactu Integration

Invoice model has verifactu fields (hash, qr_code_path, status). A Node.js worker (`worker-verifactu.js` in the Onfactu Pro repo) consumes a RabbitMQ queue and sends invoices to AEAT.

### Spanish translations

Language is set to `es` by default. Key translation keys are prefixed with `pdf_invoice_`, `pdf_proforma_invoice_`, `pdf_delivery_note_`, `navigation.*`, `settings.customization.*`. Many UI texts are hardcoded Spanish for Onfactu branding. Never change `Onfactu` to `OnFactu` (capital F is wrong).
