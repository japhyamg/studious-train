<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drive the CTR cash thresholds from the risk-scoring factor system
     * instead of a separate governance config (CBN 5.8(a)(i)).
     *
     *  - Adds TRANSACTION_AMOUNT_CORPORATE: the corporate counterpart of the
     *    existing TRANSACTION_AMOUNT factor (one amount factor per customer
     *    type).
     *  - Removes the now-obsolete per-type CTR threshold settings.
     */
    public function up(): void
    {
        DB::table('risk_scoring_configs')->insertOrIgnore([
            [
                'factor_name' => 'TRANSACTION_AMOUNT_CORPORATE',
                'factor_description' => 'Corporate Transaction Amount > ₦10,000,000',
                'weight' => 10,
                'is_active' => true,
                'conditions' => json_encode([
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'check_type' => 'transaction',
                            'field' => 'amount',
                            'operator' => 'greater_than',
                            'value' => '10000000',
                        ],
                        [
                            'check_type' => 'customer',
                            'field' => 'customer_type',
                            'operator' => 'equal_to',
                            'value' => 'corporate',
                        ],
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // The per-type CTR thresholds are now read from the amount factors above.
        DB::table('settings')
            ->whereIn('name', ['ctr_threshold_individual', 'ctr_threshold_corporate'])
            ->delete();
    }

    public function down(): void
    {
        DB::table('risk_scoring_configs')
            ->where('factor_name', 'TRANSACTION_AMOUNT_CORPORATE')
            ->delete();
    }
};
