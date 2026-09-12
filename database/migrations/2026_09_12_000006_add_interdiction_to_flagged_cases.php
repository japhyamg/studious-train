<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account interdiction flag for watchlist/PEP cases (CBN 5.3(a)(viii)).
     * Post-facto: records the decision to block/freeze an account until a
     * real-time core-banking integration exists.
     */
    public function up(): void
    {
        Schema::table('flagged_cases', function (Blueprint $table) {
            $table->string('interdiction_status', 30)->default('none')->after('report_type');
            $table->timestamp('interdicted_at')->nullable()->after('interdiction_status');
            $table->unsignedBigInteger('interdicted_by')->nullable()->after('interdicted_at');
        });
    }

    public function down(): void
    {
        Schema::table('flagged_cases', function (Blueprint $table) {
            $table->dropColumn(['interdiction_status', 'interdicted_at', 'interdicted_by']);
        });
    }
};
