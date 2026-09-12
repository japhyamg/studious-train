<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('bankId')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('account_number');
            $table->date('date_of_birth')->nullable();
            $table->string('bvn')->nullable();
            $table->string('nin')->nullable();
            $table->string('gender')->nullable();
            $table->string('isPep')->default('no');
            $table->string('customer_type')->nullable();
            $table->string('account_type')->nullable();
            $table->string('tier_level')->nullable();
            $table->string('state_of_residence')->nullable();
            $table->string('local_govt_area')->nullable();
            $table->date('date_onboarded')->nullable();
            $table->timestamps();

            $table->index('account_number');
            $table->index('bvn');
            $table->index('nin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
