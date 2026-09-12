<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_levels', function (Blueprint $table) {
            $table->integer('review_schedule_days')->nullable()->after('max_score')
                  ->comment('CDD/EDD review interval in days');
            $table->string('diligence_type')->nullable()->after('review_schedule_days')
                  ->comment('CDD or EDD');
        });
    }

    public function down(): void
    {
        Schema::table('risk_levels', function (Blueprint $table) {
            $table->dropColumn(['review_schedule_days', 'diligence_type']);
        });
    }
};
