<?php

use App\Models\CompanySetting;
use App\Models\RecurringInvoice;
use App\Space\InstallUtils;
use Illuminate\Support\Facades\Schedule;

// Onfactu: la demo pública se reinicia con demo:reiniciar, no con reset:app
// de InvoiceShelf, que hace migrate:fresh y la dejaría vacía y sin la
// configuración de Onfactu que viene de plantilla.sql.
if (config('app.env') === 'demo') {
    Schedule::command('demo:reiniciar')
        ->dailyAt('00:00')
        ->timezone('Europe/Madrid')
        ->runInBackground()
        ->withoutOverlapping();
}

if (InstallUtils::isDbCreated()) {
    Schedule::command('check:invoices:status')
        ->daily();

    Schedule::command('check:estimates:status')
        ->daily();

    $recurringInvoices = RecurringInvoice::where('status', 'ACTIVE')->get();
    foreach ($recurringInvoices as $recurringInvoice) {
        $timeZone = CompanySetting::getSetting('time_zone', $recurringInvoice->company_id);

        Schedule::call(function () use ($recurringInvoice) {
            $recurringInvoice->generateInvoice();
        })->cron($recurringInvoice->frequency)->timezone($timeZone);
    }
}
