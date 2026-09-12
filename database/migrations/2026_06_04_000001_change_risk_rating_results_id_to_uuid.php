<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add uuid column
        Schema::table('risk_rating_customer_results', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Step 2: Backfill existing records with UUIDs
        DB::table('risk_rating_customer_results')->orderBy('id')->each(function ($record) {
            DB::table('risk_rating_customer_results')
                ->where('id', $record->id)
                ->update(['uuid' => Str::uuid()->toString()]);
        });

        // Step 3: Drop old id, rename uuid to id, set as primary
        Schema::table('risk_rating_customer_results', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('risk_rating_customer_results', function (Blueprint $table) {
            $table->renameColumn('uuid', 'id');
        });

        Schema::table('risk_rating_customer_results', function (Blueprint $table) {
            $table->string('id', 36)->change();
            $table->primary('id');
        });
    }

    public function down(): void
    {
        Schema::table('risk_rating_customer_results', function (Blueprint $table) {
            $table->dropPrimary('id');
            $table->dropColumn('id');
        });

        Schema::table('risk_rating_customer_results', function (Blueprint $table) {
            $table->id()->first();
        });
    }
};
