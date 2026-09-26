<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('paper_id')->nullable()->after('test_id')->constrained('papers')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->after('paper_id')->constrained('topics')->nullOnDelete();
            $table->text('explanation_en')->nullable()->after('answer');
            $table->text('explanation_pcm')->nullable()->after('explanation_en');
        });

        // New questions belong to a paper, not a school test.
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('test_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_id');
            $table->dropConstrainedForeignId('paper_id');
            $table->dropColumn(['explanation_en', 'explanation_pcm']);
        });
    }
};
