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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // FK to users
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade'); // FK to courses
            $table->string('certificate_code')->unique(); // Unique certificate code
            $table->timestamp('issued_at')->nullable(); // Issued date
            $table->string('file_path')->nullable(); // Path to the PDF file
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};