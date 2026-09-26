<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // client_uuid is generated on the phone, so a retried upload never creates duplicates.
        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('client_uuid', 36);
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 10);                    // practice | mock
            $table->char('selected', 1)->nullable();       // null = skipped
            $table->boolean('is_correct');                 // decided by the server from the answer key
            $table->unsignedInteger('time_ms')->nullable();
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['user_id', 'question_id']);
            $table->index(['user_id', 'answered_at']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_bookmarked');
            $table->timestamp('changed_at');               // last write wins, using the phone's clock
            $table->timestamps();

            $table->unique(['user_id', 'question_id']);
            $table->index(['user_id', 'changed_at']);
        });

        Schema::create('mock_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('client_uuid', 36);
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->json('subject_scores');                // [{subject_id, correct, total}]
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->unsignedInteger('duration_seconds');
            $table->timestamp('taken_at');
            $table->timestamps();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['user_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_sessions');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('question_attempts');
    }
};
