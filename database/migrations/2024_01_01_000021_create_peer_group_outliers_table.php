<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peer_group_outliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('peer_group');
            $table->string('customer_group');
            $table->float('threshold');
            $table->float('exceeded_by')->default(0.00);
            $table->boolean('is_flagged');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_group_outliers');
    }
};
