<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactUsMessage extends Model
{
    use HasFactory;

    protected $table = 'contact_us_messages';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'subject',
        'message',
    ];
}
