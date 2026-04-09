<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Ability;
use Silber\Bouncer\Database\Role;

return new class extends Migration
{
    /**
     * Sync all abilities from config/abilities.php to existing companies.
     * Creates missing abilities and assigns them to the super admin role.
     * This ensures proforma invoice and delivery note abilities are available
     * for companies created before those abilities were added.
     */
    public function up(): void
    {
        $abilitiesConfig = config('abilities.abilities');

        foreach (Company::all() as $company) {
            BouncerFacade::scope()->to($company->id);

            // Find or create the super admin role
            $superAdmin = Role::firstOrCreate([
                'name' => 'super admin',
                'scope' => $company->id,
            ], [
                'title' => 'Super Admin',
            ]);

            foreach ($abilitiesConfig as $abilityDef) {
                // Create the ability if it doesn't exist
                $ability = Ability::firstOrCreate([
                    'name' => $abilityDef['ability'],
                    'scope' => $company->id,
                ], [
                    'title' => ucwords(str_replace('-', ' ', $abilityDef['ability'])) . ' ' . ($abilityDef['name'] ?? ''),
                    'entity_type' => $abilityDef['model'],
                ]);

                // Assign to super admin role via direct DB insert
                DB::table('permissions')->insertOrIgnore([
                    'ability_id' => $ability->id,
                    'entity_id' => $superAdmin->id,
                    'entity_type' => 'roles',
                    'scope' => $company->id,
                ]);
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
