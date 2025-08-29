<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CompanyContact extends Model
{
    protected $table = 'company_contacts';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'telephone',
        'email',
        'address',
        'social_media',
    ];

    protected $casts = [
        'social_media' => 'array',
    ];

    // Add this boot method to auto-generate UUID for id
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
