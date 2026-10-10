<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Announcements shown as a card at the top of the student's Home (website and app). Not the school-era
        // `announcements` table, which is a different thing and is left alone.
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->string('tone', 10)->default('info');
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 40)->nullable();
            $table->string('audience', 10)->default('all');
            $table->boolean('is_active')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
