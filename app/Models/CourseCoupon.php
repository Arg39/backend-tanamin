<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseCoupon extends Model
{
    protected $table = 'course_coupons';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'type',
        'value',
        'start_at',
        'end_at',
        'is_active',
        'max_usage',
        'used_count',
    ];
}