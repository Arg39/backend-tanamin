<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LessonMaterial extends Model
{
    use HasFactory;

    protected $table = 'materials';

    protected $fillable = [
        'id',
        'lesson_id',
        'content',
        'visible',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function lesson()
    {
        return $this->belongsTo(LessonCourse::class);
    }
}