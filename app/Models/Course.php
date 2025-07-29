<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $table = 'courses';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'id_category',
        'id_instructor',
        'title',
        'price',
        'discount_type',
        'discount_value',
        'is_discount_active',
        'level',
        'image',
        'status',
        'detail',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'id_category');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'id_instructor');
    }

    public function attributes()
    {
        return $this->hasMany(CourseAttribute::class, 'id_course');
    }

    public function descriptions()
    {
        return $this->hasMany(CourseAttribute::class, 'id_course')->where('type', 'description');
    }

    public function prerequisites()
    {
        return $this->hasMany(CourseAttribute::class, 'id_course')->where('type', 'prerequisite');
    }

    public function modules()
    {
        return $this->hasMany(ModuleCourse::class, 'course_id')->orderBy('order', 'asc');
    }

    // Use course_ratings for ratings
    public function ratings()
    {
        return $this->hasMany(CourseRating::class, 'id_course');
    }

    public function getAvgRatingAttribute()
    {
        return $this->ratings()->avg('rating') ?? 0;
    }

    public function getTotalRatingsAttribute()
    {
        return $this->ratings()->count();
    }

    public function reviews()
    {
        return $this->hasMany(CourseReview::class, 'id_course');
    }

    public function getTotalMaterialsAttribute()
    {
        return LessonMaterial::whereIn(
            'lesson_id',
            LessonCourse::whereIn(
                'module_id',
                ModuleCourse::where('course_id', $this->id)->pluck('id')
            )->pluck('id')
        )->count();
    }

    public function getTotalQuizAttribute()
    {
        return LessonQuiz::whereIn(
            'lesson_id',
            LessonCourse::whereIn(
                'module_id',
                ModuleCourse::where('course_id', $this->id)->pluck('id')
            )->pluck('id')
        )->count();
    }

    // filtering
    public function scopeSearch($query, $search)
    {
        return $query->where('title', 'like', '%' . $search . '%');
    }

    public function scopeCategory($query, $categoryId)
    {
        return $query->where('id_category', $categoryId);
    }

    public function scopeInstructor($query, $instructorId)
    {
        return $query->where('id_instructor', $instructorId);
    }

    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59']);
    }
}