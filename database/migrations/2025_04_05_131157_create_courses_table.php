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
            $table->uuid('id_category');
            $table->uuid('id_instructor');
            $table->string('title');
            $table->integer('price')->nullable();
            $table->string('duration')->nullable();
            $table->string('level')->nullable();
            $table->string('image_video')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->foreign('id_category')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('id_instructor')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('detail_course', function (Blueprint $table) {
            $table->uuid('id')->primary(); // shared primary key from courses.id
            $table->text('detail')->nullable();
            $table->text('description')->nullable();
            $table->text('prerequisite')->nullable();
            $table->timestamps();

            $table->foreign('id')->references('id')->on('courses')->onDelete('cascade');
        });

        Schema::create('course_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('user_id');
            $table->integer('rating');
            $table->text('review')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('detail_course');
        Schema::dropIfExists('courses');
    }
};