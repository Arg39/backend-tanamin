<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutSessionItem extends Model
{
    protected $fillable = ['course_checkout_session_id', 'course_id', 'price'];

    public function session() {
        return $this->belongsTo(CourseCheckoutSession::class, 'course_checkout_session_id');
    }

    public function course() {
        return $this->belongsTo(Course::class);
    }
}
