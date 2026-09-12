<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a status column so watchlist entries can be marked delisted and the
     * customer 360 view can show "Watchlisted" vs "Delisted" (CBN 5.3(a)(v)).
     */
    public function up(): void
    {
        Schema::table('internal_watch_lists', function (Blueprint $table) {
            $table->string('status')->default('watchlisted')->after('nin');
        });

        Schema::table('nibss_watch_lists', function (Blueprint $table) {
            $table->string('status')->default('watchlisted')->after('watchlisted_date');
        });
    }

    public function down(): void
    {
        Schema::table('internal_watch_lists', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('nibss_watch_lists', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
