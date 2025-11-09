<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Str;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'role',
        'username',
        'first_name',
        'last_name',
        'token',
        'telephone',
        'photo_profile',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Boot method to set defaults on creating.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Ensure UUID id when not provided
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            // Ensure username exists (generate from email or random)
            if (empty($model->username)) {
                if (!empty($model->email) && strpos($model->email, '@') !== false) {
                    $model->username = strtok($model->email, '@');
                } else {
                    $model->username = 'user_' . Str::random(8);
                }
            }

            // Default role if not set
            if (empty($model->role)) {
                $model->role = 'user';
            }
        });
    }

    /**
     * Get the identifier that will be stored in the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key-value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function detail()
    {
        return $this->hasOne(UserDetail::class, 'user_id', 'id');
    }

    // Relationship to courses as instructor
    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id', 'id');
    }

    public function categoriesInstructor()
    {
        return $this->belongsToMany(
            Category::class,
            'instructor_category',
            'instructor_id',
            'category_id'
        );
    }


    // Add accessor for full name
    public function getFullNameAttribute()
    {
        if ($this->first_name || $this->last_name) {
            return trim("{$this->first_name} {$this->last_name}");
        }
        return $this->name;
    }
}
