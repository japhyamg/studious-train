<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_profile_template_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('risk_profile_id')->index();
            $table->string('data_point');
            $table->string('type');
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_profile_template_data');
    }
};
