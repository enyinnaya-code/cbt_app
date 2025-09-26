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
        Schema::create('students_exams', function (Blueprint $table) {
            $table->id(); // Primary key

            // Foreign key for the user (student)
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Foreign key for the class
            $table->foreignId('class_id')
                  ->constrained('classes')
                  ->onDelete('cascade');

            // Foreign key for the test
            $table->foreignId('test_id')
                  ->constrained('tests')
                  ->onDelete('cascade');

            // Exam timing and results
            $table->timestamp('start_time')->useCurrent(); 
            $table->timestamp('exhausted_time')->nullable();
            $table->integer('score')->nullable();
            $table->integer('test_total_score');

            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students_exams');
    }
};
