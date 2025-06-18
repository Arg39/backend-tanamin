<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonQuiz extends Model
{
    use HasFactory;

    protected $table = 'quizzes';

    protected $fillable = [
        'id',
        'lesson_id',
        'title',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function lesson()
    {
        return $this->belongsTo(LessonMaterial::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'quiz_id');
    }
}
