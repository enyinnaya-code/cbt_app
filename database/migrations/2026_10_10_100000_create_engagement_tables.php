<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // XP, level and streak are worked out from question_attempts, so only badges and daily challenges are stored.
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 40);
            $table->dateTime('earned_at');
            $table->timestamps();

            $table->unique(['user_id', 'badge']);
        });

        // One challenge per student per day. The questions are fixed when it is first opened.
        Schema::create('daily_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('challenge_date');
            $table->json('question_ids');
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->unsignedSmallInteger('correct')->nullable();
            $table->unsignedSmallInteger('total');
            $table->unsignedSmallInteger('xp_awarded')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'challenge_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_challenges');
        Schema::dropIfExists('user_badges');
    }
};
