<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic store for downloaded sanction/watchlist sources (OFAC, UN
     * Consolidated, Nigerian sanctions) so screening can run against them
     * without hitting the source on every match (CBN 5.3(a)(i)/(ii)).
     */
    public function up(): void
    {
        Schema::create('watchlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->index();
            $table->string('entity_type', 30)->default('individual');
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name')->index();
            $table->json('aliases')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('date_of_birth', 20)->nullable();
            $table->string('reference')->nullable();
            $table->string('program')->nullable();
            $table->date('listed_on')->nullable();
            $table->timestamps();

            $table->index(['source', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_entries');
    }
};
