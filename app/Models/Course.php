<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'id_category',
        'id_instructor',
        'title',
        'price',
        'duration',
        'level',
        'image_video',
        'is_published',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'id_category');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'id_instructor');
    }

    public function detail()
    {
        return $this->hasOne(DetailCourse::class, 'id');
    }

    public function reviews()
    {
        return $this->hasMany(CourseReview::class, 'course_id');
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
