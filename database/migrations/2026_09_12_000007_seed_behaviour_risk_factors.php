<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add KYC/history-derived risk factors (CBN 5.5(a)(iv)): transaction
     * frequency, STR history and CTR history. These use the `behaviour` check
     * type, so they are computed over a rolling window at scoring time.
     */
    public function up(): void
    {
        DB::table('risk_scoring_configs')->insertOrIgnore([
            [
                'factor_name' => 'HIGH_FREQUENCY_TRANSACTION',
                'factor_description' => 'More than 20 transactions in 7 days',
                'weight' => 15,
                'is_active' => true,
                'conditions' => json_encode([
                    'check_type' => 'behaviour',
                    'field' => 'transaction_count',
                    'operator' => 'greater_than',
                    'value' => '20',
                    'window_days' => 7,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'factor_name' => 'PRIOR_STR_HISTORY',
                'factor_description' => 'Prior STR case within 30 days',
                'weight' => 20,
                'is_active' => true,
                'conditions' => json_encode([
                    'check_type' => 'behaviour',
                    'field' => 'str_count',
                    'operator' => 'greater_than',
                    'value' => '0',
                    'window_days' => 30,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'factor_name' => 'PRIOR_CTR_HISTORY',
                'factor_description' => 'Prior CTR case within 30 days',
                'weight' => 10,
                'is_active' => true,
                'conditions' => json_encode([
                    'check_type' => 'behaviour',
                    'field' => 'ctr_count',
                    'operator' => 'greater_than',
                    'value' => '0',
                    'window_days' => 30,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('risk_scoring_configs')
            ->whereIn('factor_name', ['HIGH_FREQUENCY_TRANSACTION', 'PRIOR_STR_HISTORY', 'PRIOR_CTR_HISTORY'])
            ->delete();
    }
};
