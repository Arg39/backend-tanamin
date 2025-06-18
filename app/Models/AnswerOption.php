<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnswerOption extends Model
{
    use HasFactory;

    protected $table = 'answer_options';

    protected $fillable = [
        'id',
        'question_id',
        'answer',
        'is_correct',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
