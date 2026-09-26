<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A paper is one past exam sitting: exam + subject + year (e.g. WAEC Chemistry 2019).
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('title')->nullable();
            $table->string('status', 16)->default('draft');   // draft | published | archived
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            // Set when the paper was created from a pre-TestaCBT school test; keeps the tagging command idempotent.
            $table->unsignedBigInteger('legacy_test_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['exam_id', 'subject_id', 'year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papers');
    }
};
