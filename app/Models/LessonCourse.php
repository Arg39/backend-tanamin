<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LessonCourse extends Model
{
    use HasFactory;

    protected $table = 'lessons';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'module_id',
        'title',
        'type',
        'order',
    ];

    /**
     * Relasi ke model ModuleCourse.
     */
    public function module()
    {
        return $this->belongsTo(ModuleCourse::class, 'module_id');
    }

    /**
     * Relasi ke model Material.
     */
    // public function materials()
    // {
    //     return $this->hasMany(Material::class, 'lesson_id');
    // }

    /**
     * Relasi ke model Question.
     */
    // public function questions()
    // {
    //     return $this->hasMany(Question::class, 'lesson_id');
    // }
}
