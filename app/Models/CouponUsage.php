<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CouponUsage extends Model
{
    protected $table = 'coupon_usages';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'course_id',
        'coupon_id',
        'used_at',
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

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    // Scopes
    public function scopeUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeCoupon($query, $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('used_at', [$start . ' 00:00:00', $end . ' 23:59:59']);
    }

    // Check if user has used a coupon for a course
    public static function hasUserUsedCoupon($userId, $courseId, $couponId)
    {
        return self::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('coupon_id', $couponId)
            ->exists();
    }
}