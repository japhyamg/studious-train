<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();
            $table->string('account_number')->nullable();
            $table->string('transaction_side')->nullable();
            $table->boolean('is_anomaly')->default(false);
            $table->float('anomaly_score')->default(0);
            $table->string('severity')->nullable();
            $table->text('anomaly_reason')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_scores');
    }
};
