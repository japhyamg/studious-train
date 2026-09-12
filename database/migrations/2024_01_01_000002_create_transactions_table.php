<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('bankId')->nullable();
            $table->string('sender_first_name');
            $table->string('sender_middle_name')->nullable();
            $table->string('sender_last_name');
            $table->string('sender_account_no');
            $table->string('sender_account_type')->nullable();
            $table->string('sender_nin')->nullable();
            $table->string('sender_bvn')->nullable();
            $table->string('beneficiary_first_name');
            $table->string('beneficiary_middle_name')->nullable();
            $table->string('beneficiary_last_name');
            $table->string('beneficiary_account_no');
            $table->string('beneficiary_account_type')->nullable();
            $table->string('beneficiary_nin')->nullable();
            $table->string('beneficiary_bvn')->nullable();
            $table->decimal('amount', 18, 8);
            $table->string('transaction_type');
            $table->string('narration')->nullable();
            $table->string('channel');
            $table->string('location')->nullable();
            $table->text('transaction_ref')->nullable();
            $table->timestamp('transaction_datetime');
            $table->timestamps();

            $table->index(['transaction_ref', 'id']);
            $table->index('sender_account_no');
            $table->index('beneficiary_account_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
