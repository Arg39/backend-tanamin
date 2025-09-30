<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Course;
use App\Models\CourseAttribute;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;

class CheckoutCourseController extends Controller
{
    public function checkoutBuyNowContent($courseId, Request $request)
    {
        try {
            $user = $request->user();

            $course = Course::select(
                'title',
                'price',
                'image',
                'discount_value',
                'discount_type',
                'discount_start_at',
                'discount_end_at',
                'is_discount_active'
            )->find($courseId);
            if (!$course) {
                return (new PostResource(false, 'Course not found', null))
                    ->response()
                    ->setStatusCode(404);
            }

            $benefits = CourseAttribute::where('course_id', $courseId)
                ->where('type', 'benefit')
                ->pluck('content')
                ->flatten()
                ->toArray();

            if ($course && $course->image) {
                $course->image = asset('storage/' . $course->image);
            }

            $basePrice = $course->price ?? 0;
            $isDiscountActive = false;
            if ($course->is_discount_active) {
                if ($course->discount_start_at && $course->discount_end_at) {
                    $isDiscountActive = now()->between($course->discount_start_at, $course->discount_end_at);
                } else {
                    $isDiscountActive = true;
                }
            }

            $discount = 0;
            if ($isDiscountActive) {
                if ($course->discount_type === 'percent') {
                    $discount = intval($basePrice * $course->discount_value / 100);
                } elseif ($course->discount_type === 'nominal') {
                    $discount = $course->discount_value;
                }
            }
            $priceAfterDiscount = max(0, $basePrice - $discount);

            $couponUsage = CouponUsage::where('course_id', $courseId)
                ->where('user_id', $user->id)
                ->first();

            $coupon = null;
            $couponDiscount = 0;
            if ($couponUsage) {
                $coupon = Coupon::where('id', $couponUsage->coupon_id)
                    ->where('is_active', true)
                    ->where('start_at', '<=', now())
                    ->where('end_at', '>=', now())
                    ->select('type', 'value')
                    ->first();

                if ($coupon) {
                    if ($coupon->type === 'percent') {
                        $couponDiscount = intval($priceAfterDiscount * $coupon->value / 100);
                    } else {
                        $couponDiscount = $coupon->value;
                    }
                }
            }

            $total = max(0, $priceAfterDiscount - $couponDiscount);

            // Hitung PPN 12%
            $ppn = intval(round($total * 0.12));
            $grandTotal = $total + $ppn;

            $response = [
                'benefit' => $benefits,
                'detail_course_checkout' => $course,
                'coupon_usage' => $coupon,
                'total' => $total,
                'ppn' => $ppn,
                'grand_total' => $grandTotal,
            ];

            // Penyesuaian pengecekan enrollment sesuai struktur tabel baru
            $courseIsEnrolled = CourseEnrollment::where('course_id', $courseId)
                ->where('user_id', $user->id)
                ->whereHas('checkoutSession', function ($query) {
                    $query->where('payment_status', 'paid');
                })
                ->exists();

            $response['already_enrolled'] = $courseIsEnrolled ? true : false;

            return new PostResource(true, 'Benefits retrieved successfully', $response);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Gagal mengambil data: ' . $e->getMessage(), null))
                ->response()
                ->setStatusCode(500);
        }
    }
}
