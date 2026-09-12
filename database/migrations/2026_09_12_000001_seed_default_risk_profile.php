<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the default risk profile so scheduled risk rating always has a
     * profile to run against (RiskRatingService resolves the isDefault profile
     * and throws when none exists).
     */
    public function up(): void
    {
        // A default profile must exist before the nightly risk-rating cron runs.
        DB::table('risk_profiles')->updateOrInsert(
            ['name' => 'Risk Profile 1'],
            [
                'data_points' => json_encode([
                    'isPep',
                    'tier_level',
                    'customer_type',
                    'account_type',
                    'state_of_residence',
                    'occupation',
                    'source_of_funds',
                    'income_range',
                    'business_activity',
                ]),
                'template_uploaded' => false,
                'isDefault' => true,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('risk_profiles')->where('name', 'Risk Profile 1')->delete();
    }
};
