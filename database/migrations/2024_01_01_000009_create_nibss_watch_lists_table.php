<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nibss_watch_lists', function (Blueprint $table) {
            $table->id();
            $table->string('requesting_bank')->nullable();
            $table->string('bvn')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('category')->nullable();
            $table->string('reason')->nullable();
            $table->string('watchlisted_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nibss_watch_lists');
    }
};
