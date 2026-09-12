<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_risk_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('state');
            $table->string('rating')->comment('Low, Medium, High');
            $table->string('score');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_risk_schedules');
    }
};
