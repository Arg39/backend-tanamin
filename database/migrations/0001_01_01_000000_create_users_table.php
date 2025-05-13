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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID type
            $table->string('role');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('telephone')->nullable();
            $table->string('password');
            $table->text('token')->nullable();
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            $table->string('photo_profile')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_detail', function (Blueprint $table) {
            $table->uuid('id_user')->primary(); // shared primary key from users.id
            $table->string('expertise')->nullable();
            $table->text('about')->nullable();
            $table->boolean('update_password')->default(false);
            $table->string('photo_cover')->nullable();
            $table->json('social_media')->nullable();
            $table->timestamps();

            $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('user_detail');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};
