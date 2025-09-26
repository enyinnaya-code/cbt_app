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
        Schema::table('students_exams', function (Blueprint $table) {
            $table->boolean('is_passed')->default(false)->after('test_total_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('students_exams', function (Blueprint $table) {
            $table->dropColumn('is_passed');
        });
    }
};
