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
    Schema::create('classes', function (Blueprint $table) {
        $table->id();
        $table->string('name');  // Class name
        $table->foreignId('section_id')->constrained('sections')->onDelete('cascade'); // Section reference
        $table->foreignId('added_by')->constrained('users')->onDelete('cascade');  // User reference
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down()
{
    Schema::dropIfExists('classes');
}
};
