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
            $table->id();

            // Foreign keys
            $table->uuid('user_id');
            $table->uuid('course_id');
            $table->uuid('coupon_id')->nullable();

            // Info pembayaran
            $table->integer('price')->nullable();
            $table->enum('payment_type', ['free', 'midtrans']);
            $table->enum('payment_status', ['pending', 'paid', 'expired'])->default('pending');

            // Info Midtrans
            $table->string('midtrans_order_id')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('transaction_status')->nullable();
            $table->string('fraud_status')->nullable();

            // Status akses kursus
            $table->enum('access_status', ['active', 'completed', 'cancelled'])->default('active');

            // Waktu penting
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('coupon_id')->references('id')->on('course_coupons')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};
