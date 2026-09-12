<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. flagged_cases.transaction_rule_id is NOT NULL, but non-rule trigger
     *    sources (watchlist, risk_score, peer_group, AI, preemptive, PAS) create
     *    cases without a rule. Make it nullable so those inserts succeed.
     * 2. ai_scores: model version + explainability note (CBN 5.4(a)(iv)).
     */
    public function up(): void
    {
        // Raw SQL avoids the doctrine/dbal requirement of ->change().
        DB::statement('ALTER TABLE `flagged_cases` MODIFY `transaction_rule_id` BIGINT UNSIGNED NULL DEFAULT NULL');

        Schema::table('ai_scores', function (Blueprint $table) {
            $table->string('model_version')->nullable()->after('anomaly_reason');
            $table->text('explanation')->nullable()->after('model_version');
        });
    }

    public function down(): void
    {
        Schema::table('ai_scores', function (Blueprint $table) {
            $table->dropColumn(['model_version', 'explanation']);
        });

        DB::statement('ALTER TABLE `flagged_cases` MODIFY `transaction_rule_id` BIGINT UNSIGNED NOT NULL');
    }
};
