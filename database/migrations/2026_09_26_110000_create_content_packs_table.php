<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per built pack version. The file itself lives on the local disk under packs/.
        Schema::create('content_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->boolean('is_current')->default(true);
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);            // of the gzip file the phone downloads
            $table->char('content_hash', 64);      // of the pack content, to skip rebuilds when nothing changed
            $table->unsignedInteger('paper_count');
            $table->unsignedInteger('question_count');
            $table->json('years');
            $table->timestamp('built_at');
            $table->timestamps();

            $table->unique(['exam_id', 'subject_id', 'version']);
            $table->index(['exam_id', 'subject_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_packs');
    }
};
