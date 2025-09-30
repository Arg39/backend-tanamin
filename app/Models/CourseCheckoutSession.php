<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CourseCheckoutSession extends Model
{
    protected $table = 'course_checkout_sessions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'checkout_type',
        'payment_status',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'transaction_status',
        'fraud_status',
        'payment_type',
        'expires_at',
        'paid_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'checkout_session_id');
    }
}
