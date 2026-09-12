<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfiu_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description');
            $table->enum('category', ['CTR', 'STR'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfiu_indicators');
    }
};
