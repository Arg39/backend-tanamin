<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCheckoutSession extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'course_checkout_sessions';

    protected $fillable = [
        'id',
        'user_id',
        'total_price',
        'payment_status',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'transaction_status',
        'fraud_status',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckoutSessionItem::class, 'course_checkout_session_id');
    }
}
