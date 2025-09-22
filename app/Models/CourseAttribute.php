<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseAttribute extends Model
{
    protected $table = 'course_attributes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'course_id',
        'type',
        'content',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
