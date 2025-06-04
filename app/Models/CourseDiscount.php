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
        'course_id',
        'type',
        'value',
        'start_at',
        'end_at',
        'is_active',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}