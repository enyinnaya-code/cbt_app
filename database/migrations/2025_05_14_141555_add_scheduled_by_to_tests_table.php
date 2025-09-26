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
            $table->unsignedBigInteger('scheduled_by')->nullable()->after('scheduled_date');
            $table->foreign('scheduled_by')->references('id')->on('users')->onDelete('set null');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropForeign(['scheduled_by']);
            $table->dropColumn('scheduled_by');
        });
    }
};
