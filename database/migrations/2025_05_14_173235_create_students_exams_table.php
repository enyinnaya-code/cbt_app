<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Schema::create('students_exams', function (Blueprint $table) {
        //     $table->id(); // Primary key
        //     $table->foreignId('id')->constrained('users')->onDelete('cascade'); // Foreign key for the user (student)
        //     $table->foreignId('class_id')->constrained('classes')->onDelete('cascade'); // Foreign key for the class
        //     $table->foreignId('test_id')->constrained('tests')->onDelete('cascade'); // Foreign key for the test
        //     $table->timestamp('start_time')->useCurrent(); // Automatically set the start time to the current timestamp
        //     $table->timestamp('exhausted_time')->nullable(); // Time when the test is exhausted, nullable by default
        //     $table->integer('score')->nullable(); // Score for the student, nullable by default
        //     $table->integer('test_total_score'); // Total score for the test
        //     $table->timestamps(); // created_at and updated_at columns
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('students_exams');
    }
};
