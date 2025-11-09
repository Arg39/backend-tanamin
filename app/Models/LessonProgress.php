<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LessonProgress extends Model
{
    protected $table = 'lesson_progresses';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'lesson_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * Boot model to generate UUID primary key when creating.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $keyName = $model->getKeyName();
            if (empty($model->{$keyName})) {
                $model->{$keyName} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user that owns the progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**Lesson
     * Get the lesson associated with the progress.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(LessonCourse::class, 'lesson_id');
    }
}
