<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CompanyPartnership extends Model
{
    protected $table = 'company_partnerships';

    protected $fillable = [
        'partner_name',
        'logo',
        'website_url',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return url('storage/' . ltrim($this->logo, '/'));
        }
        return null;
    }
}
