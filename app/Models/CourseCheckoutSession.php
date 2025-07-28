<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseCheckoutSession extends Model
{
    protected $fillable = [
        'user_id', 'total_price', 'payment_status',
        'midtrans_order_id', 'midtrans_transaction_id',
        'transaction_status', 'fraud_status', 'paid_at',
    ];

    public function items() {
        return $this->hasMany(CheckoutSessionItem::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}