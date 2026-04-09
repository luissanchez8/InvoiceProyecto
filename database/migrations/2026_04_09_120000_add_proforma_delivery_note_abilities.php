<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Silber\Bouncer\BouncerFacade;

return new class extends Migration
{
    /**
     * Sync all abilities from config/abilities.php to existing companies.
     * This ensures proforma invoice and delivery note abilities are available
     * for companies created before those abilities were added.
     */
    public function up(): void
    {
        $abilities = config('abilities.abilities');

        foreach (Company::all() as $company) {
            BouncerFacade::scope()->to($company->id);

            $superAdmin = BouncerFacade::role()->firstOrCreate([
                'name' => 'super admin',
                'title' => 'Super Admin',
                'scope' => $company->id,
            ]);

            foreach ($abilities as $ability) {
                BouncerFacade::allow($superAdmin)->to($ability['ability'], $ability['model']);
            }
        }

        BouncerFacade::refresh();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Abilities are idempotent; no rollback needed.
    }
};
