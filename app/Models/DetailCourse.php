<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetailCourse extends Model
{
    use HasFactory;

    protected $table = 'detail_course';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'detail',
        'description',
        'prerequisite',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'id');
    }
}