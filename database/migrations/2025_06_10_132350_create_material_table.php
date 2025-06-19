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
        // Create modules table
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('title');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });

        // Create lessons table
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('title');
            $table->enum('type', ['material', 'quiz', 'final_exam']);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
        });

        // Create materials table
        Schema::create('materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lesson_id');
            $table->text('content');
            $table->timestamps();

            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
        });

        // Create quizzes table
        Schema::create('quizzes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lesson_id');
            $table->string('title');
            $table->timestamps();

            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
        });

        // Create final_exams table
        Schema::create('final_exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lesson_id');
            $table->string('title');
            $table->timestamps();

            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
        });

        // Create questions table
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quiz_id')->nullable(); // filled if filled if not final_exam
            $table->uuid('final_exam_id')->nullable(); // filled if filled if not quiz
            $table->text('question');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
            $table->foreign('final_exam_id')->references('id')->on('final_exams')->onDelete('cascade');
        });

        // Create answer_options table
        Schema::create('answer_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_id');
            $table->text('answer');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
        });

        // Create material_attempts table
        Schema::create('material_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('lesson_id');
            $table->json('answers');
            $table->integer('score');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_attempts');
        Schema::dropIfExists('answer_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('final_exams');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('modules');
    }
};