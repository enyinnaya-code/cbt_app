<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per mock exam a student starts. The questions are fixed when it starts, so a refresh, a
        // dropped connection or a different device shows the same paper with the same clock.
        Schema::create('mock_runs', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->json('subject_ids');          // in the order the subjects appear
            $table->json('question_ids');         // ordered, grouped by subject
            $table->json('groups');               // [{subject_id, name, count}] in the same order
            $table->unsignedSmallInteger('minutes');
            // dateTime, not timestamp: MySQL rejects a second required timestamp column that has no default.
            $table->dateTime('started_at');
            $table->dateTime('deadline_at');
            $table->json('answers')->nullable();  // {question_id: "A"}
            $table->json('flagged')->nullable();  // [question_id]
            $table->unsignedSmallInteger('position')->default(0);
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('total')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_runs');
    }
};
