<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Bookmark extends Model
{
    protected $table = 'bookmark';

    protected $fillable = [
        'id',
        'user_id',
        'course_id',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    // Auto-generate UUID for id
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    // Relasi ke User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Relasi ke Course
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }
}
