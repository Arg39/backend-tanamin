<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CourseEnrollment extends Model
{
    protected $table = 'course_enrollments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'checkout_session_id',
        'user_id',
        'course_id',
        'coupon_id',
        'price',
        'payment_type',
        'access_status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

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

    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CourseCheckoutSession::class, 'checkout_session_id');
    }
}
