<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_results', function (Blueprint $table) {
            $table->boolean('pep_detected')->default(false)->after('risk_level');
            $table->boolean('sanctions_detected')->default(false)->after('pep_detected');
            $table->boolean('adverse_media_detected')->default(false)->after('sanctions_detected');
        });
    }

    public function down(): void
    {
        Schema::table('screening_results', function (Blueprint $table) {
            $table->dropColumn(['pep_detected', 'sanctions_detected', 'adverse_media_detected']);
        });
    }
};
