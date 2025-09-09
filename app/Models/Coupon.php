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

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Mutator untuk end_at agar selalu di-set ke jam 23:59:59 jika hanya tanggal
    public function setEndAtAttribute($value)
    {
        $dt = is_string($value) ? \Carbon\Carbon::parse($value) : $value;
        // Jika waktu jam 00:00:00, set ke 23:59:59
        if ($dt->hour === 0 && $dt->minute === 0 && $dt->second === 0) {
            $dt->setTime(23, 59, 59);
        }
        $this->attributes['end_at'] = $dt;
    }

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
