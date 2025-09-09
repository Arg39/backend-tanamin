<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSessionItem extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'checkout_session_items';

    protected $fillable = [
        'id',
        'course_checkout_session_id',
        'course_id',
        'price',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseCheckoutSession::class, 'course_checkout_session_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
