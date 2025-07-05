<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Question extends Model
{
    use HasFactory;

    protected $table = 'questions';

    protected $fillable = [
        'id',
        'quiz_id',
        'question',
        'order'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function quiz()
    {
        return $this->belongsTo(LessonQuiz::class);
    }

    public function answerOptions()
    {
        return $this->hasMany(AnswerOption::class);
    }
}