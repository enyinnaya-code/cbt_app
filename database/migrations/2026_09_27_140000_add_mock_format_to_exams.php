<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How this exam's mock works (subjects, questions, minutes), set by an admin. Empty means the standard format,
        // so exams that existed before this keep working exactly as they did.
        Schema::table('exams', function (Blueprint $table) {
            $table->json('mock_format')->nullable()->after('bundle_price');
        });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('mock_format'));
    }
};
