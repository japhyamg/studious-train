<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_rule_id')->index();
            $table->json('search_attributes');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_settings');
    }
};
