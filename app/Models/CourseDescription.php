<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseDescription extends Model
{
    protected $table = 'course_descriptions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'id_course',
        'content',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'id_course');
    }
}