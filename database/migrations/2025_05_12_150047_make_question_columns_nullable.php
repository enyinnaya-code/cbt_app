<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('question')->nullable()->change();
            $table->string('answer')->nullable()->change();
            $table->json('options')->nullable()->change();
            $table->boolean('not_question')->nullable()->change();
            $table->integer('mark')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('question')->nullable(false)->change();
            $table->string('answer')->nullable(false)->change();
            $table->json('options')->nullable(false)->change();
            $table->boolean('not_question')->nullable(false)->change();
            $table->integer('mark')->nullable(false)->change();
        });
    }
};
