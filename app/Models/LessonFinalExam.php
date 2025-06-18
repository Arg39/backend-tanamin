<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonFinalExam extends Model
{
    use HasFactory;

    protected $table = 'final_exams';

    protected $fillable = [
        'id',
        'lesson_id',
        'title',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function lesson()
    {
        return $this->belongsTo(LessonCourse::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
