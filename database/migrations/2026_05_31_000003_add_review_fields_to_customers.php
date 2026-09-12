<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('current_risk_level')->nullable()->after('local_govt_area');
            $table->float('current_risk_score')->nullable()->after('current_risk_level');
            $table->date('next_review_date')->nullable()->after('current_risk_score');
            $table->date('last_reviewed_at')->nullable()->after('next_review_date');
            $table->enum('review_status', ['pending', 'due', 'overdue', 'completed'])->default('pending')->after('last_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['current_risk_level', 'current_risk_score', 'next_review_date', 'last_reviewed_at', 'review_status']);
        });
    }
};
