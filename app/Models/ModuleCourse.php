<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModuleCourse extends Model
{
    use HasFactory;

    protected $table = 'modules';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'course_id',
        'title',
        'order',
    ];

    /**
     * Relasi ke model Course.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Relasi ke model Lesson.
     */
    public function lessons()
    {
        return $this->hasMany(LessonCourse::class, 'module_id');
    }
}
