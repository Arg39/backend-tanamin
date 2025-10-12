<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    use HasFactory;

    protected $table = 'user_details';

    // Set primary key to 'user_id'
    protected $primaryKey = 'user_id';

    // Disable auto-incrementing since we're using UUID
    public $incrementing = false;

    // Set the key type to string
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'expertise',
        'about',
        'social_media',
        'photo_cover',
        'update_password',
    ];

    protected $casts = [
        'social_media' => 'array',
    ];

    protected $attributes = [
        'update_password' => false,
    ];

    // Tambahkan relasi belongsTo ke User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
