<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A subject now has two packs: the "full" one (every question) and a small "free" one (the free sample, for
        // students who have not unlocked the subject). Each has its own version numbers.
        Schema::table('content_packs', function (Blueprint $table) {
            $table->string('tier', 8)->default('full')->after('subject_id');
            $table->unique(['exam_id', 'subject_id', 'tier', 'version'], 'content_packs_tier_version_unique');
            $table->index(['exam_id', 'subject_id', 'tier', 'is_current'], 'content_packs_tier_current_index');
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->dropUnique(['exam_id', 'subject_id', 'version']);
            $table->dropIndex(['exam_id', 'subject_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::table('content_packs', function (Blueprint $table) {
            $table->unique(['exam_id', 'subject_id', 'version']);
            $table->index(['exam_id', 'subject_id', 'is_current']);
        });

        Schema::table('content_packs', function (Blueprint $table) {
            $table->dropUnique('content_packs_tier_version_unique');
            $table->dropIndex('content_packs_tier_current_index');
            $table->dropColumn('tier');
        });
    }
};
