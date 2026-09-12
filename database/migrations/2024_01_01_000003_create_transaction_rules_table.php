<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('search_attributes');
            $table->string('type')->comment('Global, Custom')->default('Global');
            $table->string('run_time', 11)->comment('Instantly, 24HrTask')->default('Instantly');
            $table->string('report_type')->default('STR');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_rules');
    }
};
