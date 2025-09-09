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
        return $this->hasMany(CourseReview::class, 'id_course', 'id');
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

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class, 'course_id', 'id');
    }
}
