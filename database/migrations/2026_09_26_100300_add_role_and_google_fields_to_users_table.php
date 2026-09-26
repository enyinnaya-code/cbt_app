<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('student')->after('user_type');
            $table->string('google_id')->nullable()->unique()->after('role');
            $table->string('avatar_url')->nullable()->after('google_id');
            $table->json('preferred_exams')->nullable()->after('avatar_url');
            $table->index('role');
        });

        // Google-only accounts have no password.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        // Legacy user_type: 1 super admin, 2 admin, 3 teacher, 4 student.
        DB::table('users')->whereIn('user_type', [1, 2])->update(['role' => 'admin']);
        DB::table('users')->where('user_type', 3)->update(['role' => 'examiner']);
        DB::table('users')->whereNotIn('user_type', [1, 2, 3])->update(['role' => 'student']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['google_id']);
            $table->dropColumn(['role', 'google_id', 'avatar_url', 'preferred_exams']);
        });
    }
};
