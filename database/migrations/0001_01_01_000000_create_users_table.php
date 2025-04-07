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
            $table->id(); // Primary key
            $table->string('role'); // Role of the user (e.g., admin, student, etc.)
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('telephone')->nullable();
            $table->string('password');
            $table->string('token')->nullable(); // For authentication or verification
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            $table->string('photo_profile')->nullable(); // Path to profile photo
            $table->timestamps(); // created_at and updated_at
            $table->softDeletes(); // deleted_at
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary(); // Session ID
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade'); // Optional user ID
            $table->string('ip_address', 45)->nullable(); // IP address of the session
            $table->text('user_agent')->nullable(); // User agent of the session
            $table->text('payload'); // Session data
            $table->integer('last_activity'); // Last activity timestamp
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};