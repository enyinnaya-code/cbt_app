<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->timestamp('approval_date')->nullable()->after('is_approved');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_date');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('is_submitted');

            //Optional: Add foreign key constraints if you want to link to the users table
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn(['approval_date', 'approved_by', 'submitted_by']);
        });
    }
};
