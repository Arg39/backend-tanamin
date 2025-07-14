<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyPartnership extends Model
{
    protected $table = 'company_partnerships';

    protected $fillable = [
        'partner_name',
        'logo',
        'website_url',
    ];
}
