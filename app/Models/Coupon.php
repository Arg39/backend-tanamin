<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Coupon extends Model
{
    protected $table = 'coupons';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'code',
        'type',
        'value',
        'start_at',
        'end_at',
        'is_active',
        'max_usage',
        'used_count',
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

    // Tambahkan relasi ke CouponUsage
    public function usages()
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id', 'id');
    }
}