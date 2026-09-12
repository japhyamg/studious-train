<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('occupation')->nullable()->after('local_govt_area');
            $table->string('source_of_funds')->nullable()->after('occupation');
            $table->string('income_range')->nullable()->after('source_of_funds');
            $table->string('business_activity')->nullable()->after('income_range');
            $table->string('employer_name')->nullable()->after('business_activity');
            $table->string('phone_number')->nullable()->after('employer_name');
            $table->string('email')->nullable()->after('phone_number');
            $table->text('address')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'occupation', 'source_of_funds', 'income_range',
                'business_activity', 'employer_name', 'phone_number',
                'email', 'address',
            ]);
        });
    }
};
