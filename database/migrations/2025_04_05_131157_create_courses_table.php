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
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->uuid('instructor_id');
            $table->string('title');
            // detail
            $table->string('image')->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advance'])->nullable();
            $table->enum('status', ['new', 'edited', 'awaiting_approval', 'published'])->default('new');
            $table->text('detail')->nullable();
            // price & discount
            $table->integer('price')->nullable();
            $table->enum('discount_type', ['percent', 'nominal'])->nullable();
            $table->integer('discount_value')->nullable();
            $table->dateTime('discount_start_at')->nullable();
            $table->dateTime('discount_end_at')->nullable();
            $table->boolean('is_discount_active')->default(false);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('instructor_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('course_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->enum('type', ['description', 'prerequisite']);
            $table->text('content');
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('course_attributes');
        Schema::dropIfExists('courses');
    }
};
