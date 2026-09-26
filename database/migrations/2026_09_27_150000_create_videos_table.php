<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Videos on YouTube, Facebook, X or TikTok that an admin wants shown on the website. Only the link is stored;
        // the player address is worked out from it when it is saved.
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description', 400)->nullable();
            $table->string('platform', 12)->index();          // youtube, facebook, x, tiktok
            $table->string('external_id', 80)->nullable();
            $table->string('url', 500);                       // the clean address of the video on its own site
            $table->string('embed_url', 700);                 // the player, built from the id
            $table->string('thumbnail_url', 300)->nullable(); // only YouTube gives one without asking
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->string('status', 12)->default('published')->index();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
