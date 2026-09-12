<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flagged_cases', function (Blueprint $table) {
            // Add trigger source — what caused this case
            $table->string('trigger_source')->default('rule')->after('type')
                  ->comment('rule, watchlist, risk_score, ai_anomaly, peer_group, manual');
            $table->text('trigger_details')->nullable()->after('trigger_source')
                  ->comment('JSON details of what triggered');

            // Track which account side was flagged
            $table->string('flagged_side')->nullable()->after('account_no')
                  ->comment('sender, beneficiary, both');

            // Link to the customer if resolved
            $table->unsignedBigInteger('customer_id')->nullable()->after('account_no');
        });
    }

    public function down(): void
    {
        Schema::table('flagged_cases', function (Blueprint $table) {
            $table->dropColumn(['trigger_source', 'trigger_details', 'flagged_side', 'customer_id']);
        });
    }
};
