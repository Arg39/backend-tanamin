<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

    public function setEndAtAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['end_at'] = null;
            return;
        }

        if (is_string($value)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $dt = Carbon::createFromFormat('Y-m-d', $value)->setTime(23, 59, 59);
                $this->attributes['end_at'] = $dt;
                return;
            }

            $dt = Carbon::parse($value);
            $this->attributes['end_at'] = $dt;
            return;
        }

        if ($value instanceof \DateTimeInterface) {
            $dt = Carbon::instance($value);
            $this->attributes['end_at'] = $dt;
            return;
        }

        $dt = Carbon::parse($value);
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

    public function usages()
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id', 'id');
    }
}
