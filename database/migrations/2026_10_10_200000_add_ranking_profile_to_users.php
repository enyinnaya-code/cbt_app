<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a student chooses to show on the rankings instead of their real name. Nothing here is filled in until they
        // opt in, and leaving the rankings clears it again.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('leaderboard_opt_in')->default(false)->after('target_exam_date');
            $table->string('nickname', 24)->nullable()->after('leaderboard_opt_in');
            $table->string('nickname_key', 24)->nullable()->unique()->after('nickname');   // lower case, so two spellings cannot clash
            $table->string('avatar', 24)->nullable()->after('nickname_key');
            $table->string('school', 80)->nullable()->after('avatar');
            $table->string('school_key', 80)->nullable()->index()->after('school');        // lower case, for grouping the school board
            $table->dateTime('privacy_accepted_at')->nullable()->after('school_key');
            $table->string('privacy_version', 12)->nullable()->after('privacy_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nickname_key']);
            $table->dropIndex(['school_key']);
            $table->dropColumn(['leaderboard_opt_in', 'nickname', 'nickname_key', 'avatar', 'school', 'school_key', 'privacy_accepted_at', 'privacy_version']);
        });
    }
};
