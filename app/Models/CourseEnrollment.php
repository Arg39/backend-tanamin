<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'user_id', 'course_id', 'coupon_id', 'price', 'payment_type',
        'payment_status', 'midtrans_order_id', 'midtrans_transaction_id',
        'transaction_status', 'fraud_status', 'access_status', 'enrolled_at', 'expired_at',
    ];

    public function course() {
        return $this->belongsTo(Course::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}