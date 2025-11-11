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
        Schema::create('course_checkout_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');

            $table->enum('checkout_type', ['cart', 'direct']);
            $table->enum('payment_status', ['pending', 'paid', 'expired'])->default('pending');

            // midtrans info
            $table->string('midtrans_order_id')->nullable()->unique();
            $table->string('midtrans_transaction_id')->nullable();
            $table->enum('transaction_status', ['pending', 'capture', 'settlement', 'deny', 'expire', 'cancel'])->nullable();
            $table->enum('fraud_status', ['accept', 'challenge', 'deny'])->nullable();
            $table->string('payment_type')->nullable();

            // important time 
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('checkout_session_id');
            $table->uuid('user_id');
            $table->uuid('course_id');
            $table->uuid('coupon_id')->nullable();
            $table->integer('price')->nullable();
            $table->enum('payment_type', ['free', 'midtrans', 'pending'])->default('pending');
            $table->enum('access_status', ['inactive', 'active', 'completed'])->default('inactive');
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
            $table->foreign('checkout_session_id')->references('id')->on('course_checkout_sessions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_checkout_sessions');
    }
};