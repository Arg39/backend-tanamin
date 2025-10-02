<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardCourseResource;
use App\Http\Resources\PostResource;
use App\Models\Course;
use App\Models\CourseCheckoutSession;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function getCartCourses(Request $request)
    {
        $user = $request->user();

        // Cari session cart aktif user
        $cartSession = CourseCheckoutSession::where('user_id', $user->id)
            ->where('checkout_type', 'cart')
            ->where('payment_status', 'pending')
            ->first();

        if (!$cartSession) {
            return new PostResource(true, 'Cart kosong.', []);
        }

        // Ambil semua enrollment di cart session ini
        $enrollments = CourseEnrollment::where('checkout_session_id', $cartSession->id)
            ->where('user_id', $user->id)
            ->where('access_status', 'inactive')
            ->get();

        if ($enrollments->isEmpty()) {
            return new PostResource(true, 'Cart kosong.', []);
        }

        // Ambil data kursus
        $courses = Course::whereIn('id', $enrollments->pluck('course_id'))->get();

        // Tandai in_cart true di setiap course
        $courses->each(function ($course) {
            $course->in_cart = true;
        });

        $resource = CardCourseResource::collection($courses)->resolve($request);

        return new PostResource(true, 'Daftar kursus di cart berhasil diambil.', $resource);
    }

    public function addToCart($courseId, Request $request)
    {

        $user = $request->user();

        // Cek course
        $course = Course::findOrFail($courseId);

        // Cek apakah user sudah punya session cart yang aktif
        $cartSession = CourseCheckoutSession::where('user_id', $user->id)
            ->where('checkout_type', 'cart')
            ->where('payment_status', 'pending')
            ->first();

        // Jika belum ada, buat session cart baru
        if (!$cartSession) {
            $cartSession = CourseCheckoutSession::create([
                'user_id' => $user->id,
                'checkout_type' => 'cart',
                'payment_status' => 'pending',
            ]);
        }
        // bd38c9e2-c3c5-4463-a807-e7e9bbacc38e

        // Cek apakah course sudah ada di cart
        $alreadyInCart = CourseEnrollment::where('checkout_session_id', $cartSession->id)
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('access_status', 'inactive')
            ->first();

        if ($alreadyInCart) {
            return new PostResource(false, 'Course sudah ada di cart.', null);
        }

        // Hitung harga (bisa tambahkan diskon jika ada)
        $price = $course->price;
        if ($course->active_discount && $course->discount_value) {
            if ($course->discount_type === 'percent') {
                $price = max(0, $price - ($price * $course->discount_value / 100));
            } else {
                $price = max(0, $price - $course->discount_value);
            }
        }

        // Tentukan payment_type
        $paymentType = $price <= 0 ? 'free' : 'midtrans';

        // Tambahkan ke cart (enrollment)
        $enrollment = CourseEnrollment::create([
            'checkout_session_id' => $cartSession->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => $price,
            'payment_type' => $paymentType,
            'access_status' => 'inactive',
        ]);

        return new PostResource(true, 'Course berhasil ditambahkan ke cart.', $enrollment);
    }
}
