<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfiu_indicators', function (Blueprint $table) {
            $table->text('description')->change();
            $table->string('category')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('nfiu_indicators', function (Blueprint $table) {
            $table->string('description')->change();
            $table->enum('category', ['CTR', 'STR'])->nullable()->change();
        });
    }
};
