<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Small key/value store for things an admin edits in the console (store links, bank details, prices).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // News, exam news, result releases, scholarships and blog articles, all written by admins.
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30)->index();       // news, exam-news, results, scholarship, blog
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 300)->nullable();
            $table->longText('body');
            $table->string('cover_path')->nullable();
            $table->string('status', 12)->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->dateTime('published_at')->nullable()->index();
            $table->date('deadline')->nullable();          // scholarships: last day to apply
            $table->string('source')->nullable();          // scholarships: who is offering it
            $table->string('link_url', 500)->nullable();   // scholarships: where to apply
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Upcoming dates students care about: exam days, registration deadlines, result releases, webinars.
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('kind', 20)->default('other');  // exam, registration, results, webinar, other
            $table->text('description')->nullable();
            $table->date('starts_on')->index();
            $table->date('ends_on')->nullable();
            $table->string('location')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('status', 12)->default('published')->index();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('settings');
    }
};
