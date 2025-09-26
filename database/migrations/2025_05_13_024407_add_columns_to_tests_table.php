<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->boolean('is_submitted')->default(0);
            $table->boolean('is_approved')->default(0);
            $table->text('comments')->nullable(); // You can set nullable if you want to allow empty values
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn(['is_submitted', 'is_approved', 'comments']);
        });
    }
};
