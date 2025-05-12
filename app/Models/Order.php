<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_code',
        'name',
        'email',
        'amount',
        'payment_status',
        'midtrans_response',
    ];

    protected $casts = [
        'midtrans_response' => 'array',
    ];
}
