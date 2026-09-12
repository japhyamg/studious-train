<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flagged_case_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flagged_case_id')->index();
            $table->text('comment');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flagged_case_comments');
    }
};
