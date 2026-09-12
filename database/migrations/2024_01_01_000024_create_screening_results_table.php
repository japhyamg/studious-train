<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_results', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('fullname')->nullable();
            $table->string('slug')->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->json('search_context')->nullable();
            $table->string('pep_status')->default('NO_MATCH');
            $table->json('pep_matches')->nullable();
            $table->json('pep_meta')->nullable();
            $table->string('sanction_status')->default('NOT_LISTED');
            $table->json('sanction_matches')->nullable();
            $table->string('adverse_media_status')->default('NO_MATCH');
            $table->json('adverse_media_articles')->nullable();
            $table->string('risk_level')->default('LOW');
            $table->timestamp('screened_at')->nullable();
            $table->unsignedBigInteger('screened_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_results');
    }
};
