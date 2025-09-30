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
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign keys
            $table->uuid('user_id');
            $table->uuid('course_id');
            $table->uuid('coupon_id')->nullable();

            // Info pembayaran
            $table->integer('price')->nullable();
            $table->enum('payment_type', ['free', 'midtrans']);
            $table->enum('payment_status', ['pending', 'paid', 'expired'])->default('pending');

            // Info Midtrans
            $table->string('midtrans_order_id')->nullable()->unique();
            $table->string('midtrans_transaction_id')->nullable();
            $table->enum('transaction_status', ['capture', 'settlement', 'pending', 'deny', 'expire', 'cancel'])->nullable();
            $table->enum('fraud_status', ['accept', 'challenge', 'deny'])->nullable();

            // Status akses kursus
            $table->enum('access_status', ['active', 'completed', 'cancelled'])->default('active');

            // Waktu penting
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->unique(['user_id', 'course_id']);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
        });

        Schema::create('course_checkout_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->integer('total_price')->nullable();

            $table->enum('payment_status', ['pending', 'paid', 'expired'])->default('pending');
            $table->string('midtrans_order_id')->nullable()->unique();
            $table->string('midtrans_transaction_id')->nullable();
            $table->enum('transaction_status', ['capture', 'settlement', 'pending', 'deny', 'expire', 'cancel'])->nullable();
            $table->enum('fraud_status', ['accept', 'challenge', 'deny'])->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('checkout_session_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('course_checkout_session_id');
            $table->uuid('course_id');
            $table->integer('price')->nullable();

            $table->timestamps();

            $table->foreign('course_checkout_session_id')->references('id')->on('course_checkout_sessions')->onDelete('cascade');

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_session_items');
        Schema::dropIfExists('course_checkout_sessions');
        Schema::dropIfExists('course_enrollments');
    }
};
