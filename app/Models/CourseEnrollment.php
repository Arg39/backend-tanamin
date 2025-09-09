<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'course_enrollments';

    protected $fillable = [
        'id',
        'user_id',
        'course_id',
        'coupon_id',
        'price',
        'payment_type',
        'payment_status',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'transaction_status',
        'fraud_status',
        'access_status',
        'enrolled_at',
        'expired_at',
        'paid_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
