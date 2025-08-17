<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorCategory extends Model
{
    protected $table = 'instructor_category';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'instructor_id',
        'category_id',
    ];

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
