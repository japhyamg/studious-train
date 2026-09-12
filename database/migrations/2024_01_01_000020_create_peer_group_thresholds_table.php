<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peer_group_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('name_key');
            $table->string('name');
            $table->json('thresholds');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_group_thresholds');
    }
};
