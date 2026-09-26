<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Price of one subject of one exam, in whole naira. Empty means "use the site default" (Console > Settings);
        // 0 means the whole subject is free. free_questions is how many questions a student can try before paying.
        Schema::table('exam_subject', function (Blueprint $table) {
            $table->unsignedInteger('price')->nullable()->after('display_name');
            $table->unsignedSmallInteger('free_questions')->nullable()->after('price');
        });

        // Optional price for every subject of an exam at once. Empty means no bundle is offered.
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedInteger('bundle_price')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('bundle_price'));
        Schema::table('exam_subject', fn (Blueprint $table) => $table->dropColumn(['price', 'free_questions']));
    }
};
