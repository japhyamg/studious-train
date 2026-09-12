<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_scoring_configs', function (Blueprint $table) {
            $table->id();
            $table->string('factor_name')->unique();
            $table->string('factor_description');
            $table->integer('weight');
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable();
            $table->timestamps();
        });

        DB::table('risk_scoring_configs')->insert([
            [
                'factor_name' => 'TRANSACTION_AMOUNT',
                'factor_description' => 'Transaction Amount > ₦1,000,000',
                'weight' => 10,
                'is_active' => true,
                'conditions' => json_encode([
                    'check_type' => 'transaction',
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => '1000000',
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'factor_name' => 'PEP_STATUS',
                'factor_description' => 'Customer is a Politically Exposed Person',
                'weight' => 40,
                'is_active' => true,
                'conditions' => json_encode([
                    'check_type' => 'customer',
                    'field' => 'isPep',
                    'operator' => 'is_true',
                    'value' => 'yes',
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'factor_name' => 'CUSTOMER_RISK_RATE_HIGH',
                'factor_description' => 'Customer Risk Rating - High',
                'weight' => 25,
                'is_active' => true,
                'conditions' => json_encode([
                    'check_type' => 'customer',
                    'field' => 'current_risk_level',
                    'operator' => 'equal_to',
                    'value' => 'high',
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'factor_name' => 'CUSTOMER_RISK_RATE_MEDIUM',
                'factor_description' => 'Customer Risk Rating - Medium',
                'weight' => 25,
                'is_active' => true,
                'conditions' => json_encode([
                    'check_type' => 'customer',
                    'field' => 'current_risk_level',
                    'operator' => 'equal_to',
                    'value' => 'medium',
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_scoring_configs');
    }
};
