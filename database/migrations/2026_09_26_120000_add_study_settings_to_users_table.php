<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('explanation_language', 3)->default('en')->after('preferred_exams');   // en | pcm
            $table->string('target_exam', 40)->nullable()->after('explanation_language');          // exam slug the countdown refers to
            $table->date('target_exam_date')->nullable()->after('target_exam');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['explanation_language', 'target_exam', 'target_exam_date']);
        });
    }
};
