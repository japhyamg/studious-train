<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only history of customer risk classification changes and the
     * driver of each change, to satisfy CBN baseline 5.4(a)(v) ("generate
     * reports on changes in customer risk classification and the drivers of
     * such changes").
     */
    public function up(): void
    {
        Schema::create('risk_level_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('from_level')->nullable();
            $table->string('to_level')->nullable();
            $table->float('score')->nullable();
            $table->string('driver')->default('risk_rating')->comment('risk_rating, scheduled_rating, event_driven, manual');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_level_changes');
    }
};
