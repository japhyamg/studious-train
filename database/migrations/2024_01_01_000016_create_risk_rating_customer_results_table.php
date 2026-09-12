<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_rating_customer_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_rating_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->float('score');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_rating_customer_results');
    }
};
