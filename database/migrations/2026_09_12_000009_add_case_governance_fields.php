<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 — case governance schema.
     *
     * 5.1  risk_levels.case_tat_hours          — TAT/SLA per risk level
     *      flagged_cases.sla_due_at             — computed SLA deadline
     * 5.2  flagged_cases.proposed_* / approved_* — maker-checker disposition
     * 5.4  flagged_cases.filing_status/filed_*   — goAML filing state
     * 5.5  users.last_login_*                    — device/IP logging
     */
    public function up(): void
    {
        Schema::table('risk_levels', function (Blueprint $table) {
            $table->unsignedInteger('case_tat_hours')->nullable()->after('review_schedule_days');
        });

        Schema::table('flagged_cases', function (Blueprint $table) {
            $table->timestamp('sla_due_at')->nullable()->after('report_type');

            $table->string('proposed_status', 30)->nullable()->after('sla_due_at');
            $table->unsignedBigInteger('proposed_by')->nullable()->after('proposed_status');
            $table->timestamp('proposed_at')->nullable()->after('proposed_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('proposed_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->string('filing_status', 20)->default('draft')->after('approved_at');
            $table->timestamp('filed_at')->nullable()->after('filing_status');
            $table->unsignedBigInteger('filed_by')->nullable()->after('filed_at');
            $table->string('filing_reference')->nullable()->after('filed_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('email_otp_enabled');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->string('last_login_device')->nullable()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'last_login_ip', 'last_login_device']);
        });

        Schema::table('flagged_cases', function (Blueprint $table) {
            $table->dropColumn([
                'sla_due_at',
                'proposed_status', 'proposed_by', 'proposed_at', 'approved_by', 'approved_at',
                'filing_status', 'filed_at', 'filed_by', 'filing_reference',
            ]);
        });

        Schema::table('risk_levels', function (Blueprint $table) {
            $table->dropColumn('case_tat_hours');
        });
    }
};
