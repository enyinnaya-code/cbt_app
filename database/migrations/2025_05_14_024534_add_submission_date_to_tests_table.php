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
            $table->timestamp('submission_date')->nullable()->after('scheduled_date'); // adjust "after" as needed
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn('submission_date');
        });
    }
};
