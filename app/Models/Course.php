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
        'category_id',
        'instructor_id',
        'title',
        'image',
        'level',
        'status',
        'detail',
        'price',
        'discount_type',
        'discount_value',
        'discount_start_at',
        'discount_end_at',
        'is_discount_active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function attributes()
    {
        return $this->hasMany(CourseAttribute::class, 'course_id');
    }

    public function descriptions()
    {
        return $this->hasMany(CourseAttribute::class, 'course_id')->where('type', 'description');
    }

    public function prerequisites()
    {
        return $this->hasMany(CourseAttribute::class, 'course_id')->where('type', 'prerequisite');
    }

    public function modules()
    {
        return $this->hasMany(ModuleCourse::class, 'course_id', 'id');
    }

    public function getActiveDiscountAttribute()
    {
        return $this->is_discount_active ? true : false;
    }

    public function getActiveDiscountValueAttribute()
    {
        return $this->active_discount ? $this->discount_value : null;
    }

    public function getActiveDiscountTypeAttribute()
    {
        return $this->active_discount ? $this->discount_type : null;
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'course_participants', 'course_id', 'user_id');
    }

    public function reviews()
    {
        return $this->hasMany(CourseReview::class, 'course_id', 'id');
    }

    public function getAvgRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    public function getTotalRatingsAttribute()
    {
        return $this->reviews()->count();
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

    // 🔎 Flexible search (pecah kata agar lebih cocok)
    public function scopeSearch($query, $search)
    {
        $terms = preg_split('/\s+/', trim($search)); // pecah berdasarkan spasi
        return $query->where(function ($q) use ($terms) {
            foreach ($terms as $term) {
                $q->where('title', 'like', '%' . $term . '%');
            }
        });
    }

    public function scopeCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeInstructor($query, $instructorId)
    {
        return $query->where('instructor_id', $instructorId);
    }

    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59']);
    }

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class, 'course_id', 'id');
    }
}
