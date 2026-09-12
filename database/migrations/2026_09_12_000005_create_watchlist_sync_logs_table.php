<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for sanction/watchlist refreshes — per-list version,
     * last-updated and record count so the register's "list-update logs"
     * requirement (CBN 5.3(a)(iii)/(iv)) is evidenced.
     */
    public function up(): void
    {
        Schema::create('watchlist_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->index();
            $table->string('status', 20)->default('success');
            $table->unsignedInteger('record_count')->default(0);
            $table->string('version')->nullable();
            $table->date('last_updated')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_sync_logs');
    }
};
