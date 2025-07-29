<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseDiscount extends Model
{
    protected $table = 'course_discounts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'value',
        'start_at',
        'end_at',
        'is_active',
    ];
}