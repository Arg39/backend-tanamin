<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourseRating extends Model
{
    use HasFactory;

    protected $table = 'course_ratings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'id_user',
        'id_course',
        'rating',
        'comment',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'id_course', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    /**
     * Count total ratings for a course.
     */
    public static function countForCourse($courseId)
    {
        return self::where('id_course', $courseId)->count();
    }

    /**
     * Calculate average rating for a course.
     */
    public static function averageForCourse($courseId)
    {
        return self::where('id_course', $courseId)->avg('rating');
    }
}