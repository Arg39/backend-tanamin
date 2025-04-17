<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    use HasFactory;

    protected $table = 'user_detail';

    // Set primary key to 'id_user'
    protected $primaryKey = 'id_user';

    // Disable auto-incrementing since we're using UUID
    public $incrementing = false;

    // Set the key type to string
    protected $keyType = 'string';

    protected $fillable = [
        'id_user',
        'expertise',
        'about',
        'social_media',
        'photo_cover',
        'update_password',
    ];

    protected $casts = [
        'social_media' => 'array',
    ];
}
