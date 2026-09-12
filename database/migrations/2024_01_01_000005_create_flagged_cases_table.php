<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flagged_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_rule_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->json('transaction_ids')->nullable();
            $table->string('slug')->unique();
            $table->string('account_no')->nullable();
            $table->enum('type', ['transaction', 'account'])->default('transaction');
            $table->enum('status', ['open', 'closed_filed', 'closed_not_filed', 'escalated'])->default('open');
            $table->enum('classification', ['true_positive', 'false_positive'])->default('true_positive');
            $table->enum('report_type', ['CTR', 'STR'])->default('CTR');
            $table->unsignedBigInteger('indicator_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('closed_by')->nullable()->index();
            $table->timestamps();

            $table->index('transaction_rule_id');
            $table->index('account_no');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flagged_cases');
    }
};
