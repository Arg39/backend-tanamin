<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyStatistics extends Model
{
    protected $table = 'company_statistics';

    protected $fillable = [
        'title',
        'value',
        'unit',
    ];
}
