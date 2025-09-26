<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSectionsTable extends Migration
{
    public function up()
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id(); // Auto-incrementing ID
            $table->string('section_name'); // Section name
            $table->unsignedBigInteger('created_by'); // Created by user (foreign key to users table)
            $table->timestamps(); // Created at and updated at timestamps

            // Optional: Foreign key constraint to link with users table (if users exist)
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sections');
    }
}
