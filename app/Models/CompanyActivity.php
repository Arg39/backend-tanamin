<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CompanyActivity extends Model
{
    protected $table = 'company_activities';

    protected $fillable = [
        'id',
        'image',
        'title',
        'description',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return url('storage/' . ltrim($this->image, '/'));
        }
        return null;
    }
}
