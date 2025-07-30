<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourseReview extends Model
{
    use HasFactory;

    protected $table = 'course_reviews';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'id_course',
        'id_user',
        'rating',
        'comment',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'id_course');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}