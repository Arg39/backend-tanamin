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
        'id_course',
        'type',
        'content',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'id_course');
    }
}
