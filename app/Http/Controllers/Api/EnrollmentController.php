<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Resources\PostResource;

class EnrollmentController extends Controller
{
    public function buyNow(Request $request, $courseId)
    {
        try {
            $user = $request->user();
            $course = Course::findOrFail($courseId);

            // Cek apakah sudah pernah dibeli
            $alreadyEnrolled = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('payment_status', 'paid')
                ->exists();

            if ($alreadyEnrolled) {
                return (new PostResource(false, 'Kursus ini sudah dibeli.', null))->response()->setStatusCode(400);
            }

            // Hitung harga awal
            $basePrice = $course->price ?? 0;

            // ✅ Cek diskon aktif (lebih fleksibel)
            $isDiscountActive = false;
            if ($course->is_discount_active) {
                if ($course->discount_start_at && $course->discount_end_at) {
                    $isDiscountActive = now()->between($course->discount_start_at, $course->discount_end_at);
                } else {
                    // Kalau tanggal kosong tapi is_discount_active = true → tetap aktif
                    $isDiscountActive = true;
                }
            }

            // Hitung diskon
            $discount = 0;
            if ($isDiscountActive) {
                if ($course->discount_type === 'percent') {
                    $discount = intval($basePrice * $course->discount_value / 100);
                } elseif ($course->discount_type === 'nominal') {
                    $discount = $course->discount_value;
                }
            }

            $priceAfterDiscount = max(0, $basePrice - $discount);

            // Cek coupon usage dari user untuk course ini
            $couponUsage = \App\Models\CouponUsage::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            $coupon = null;
            $couponId = null;
            $couponDiscount = 0;

            if ($couponUsage) {
                $coupon = Coupon::where('id', $couponUsage->coupon_id)
                    ->where('is_active', true)
                    ->where('start_at', '<=', now())
                    ->where('end_at', '>=', now())
                    ->first();

                if ($coupon) {
                    // Cek usage limit
                    if ($coupon->max_usage !== null && $coupon->used_count >= $coupon->max_usage) {
                        return (new PostResource(false, 'Kupon sudah mencapai batas penggunaan.', null))->response()->setStatusCode(400);
                    }

                    // Hitung diskon kupon
                    if ($coupon->type === 'percent') {
                        $couponDiscount = intval($priceAfterDiscount * $coupon->value / 100);
                    } else {
                        $couponDiscount = $coupon->value;
                    }
                    $couponId = $coupon->id;
                }
            }

            $finalPrice = max(0, $priceAfterDiscount - $couponDiscount);

            // Jika kursus gratis (setelah diskon dan/atau kupon)
            if ($finalPrice <= 0) {
                $enrollment = CourseEnrollment::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'coupon_id' => $couponId,
                    'price' => 0,
                    'payment_type' => 'free',
                    'payment_status' => 'paid',
                    'access_status' => 'active',
                    'enrolled_at' => now(),
                    'paid_at' => now(),
                ]);

                // Jika ada coupon, catat penggunaan coupon (hanya jika belum pernah)
                if ($couponId && !$couponUsage) {
                    \App\Models\CouponUsage::firstOrCreate([
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'coupon_id' => $couponId,
                    ], [
                        'used_at' => now(),
                    ]);
                    Coupon::where('id', $couponId)->increment('used_count');
                }

                return new PostResource(true, 'Kursus berhasil diakses secara gratis.', [
                    'enrollment_id' => $enrollment->id,
                ]);
            }

            // Cek apakah sudah ada enrollment pending untuk user dan course ini
            $pendingEnrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('payment_status', 'pending')
                ->first();

            if ($pendingEnrollment) {
                $orderId = $pendingEnrollment->midtrans_order_id;

                // Update harga/coupon jika ada perubahan
                $pendingEnrollment->update([
                    'price' => $finalPrice,
                    'coupon_id' => $couponId,
                ]);

                $midtrans = MidtransService::createTransaction([
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => (int)$finalPrice,
                    ],
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                    ],
                ]);

                return new PostResource(true, 'Silakan lanjutkan pembayaran.', [
                    'redirect_url' => $midtrans->redirect_url,
                ]);
            }

            // Buat transaksi Midtrans baru
            $orderId = 'ORDER-' . strtoupper(Str::uuid());
            $midtrans = MidtransService::createTransaction([
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int)$finalPrice,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

            CourseEnrollment::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'course_id' => $course->id,
                'coupon_id' => $couponId,
                'price' => $finalPrice,
                'payment_type' => 'midtrans',
                'payment_status' => 'pending',
                'midtrans_order_id' => $orderId,
            ]);

            return new PostResource(true, 'Silakan lanjutkan pembayaran.', [
                'redirect_url' => $midtrans->redirect_url,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return (new PostResource(false, 'Validasi gagal', $e->errors()))->response()->setStatusCode(422);
        } catch (\Exception $e) {
            return (new PostResource(false, 'Terjadi kesalahan pada server.', null))->response()->setStatusCode(500);
        }
    }

    public function midtransCallback(Request $request)
    {
        Log::info('Midtrans callback hit', ['payload' => $request->all()]);

        try {
            $data = $request->all();

            if (empty($data['order_id'])) {
                Log::warning('Callback received without order_id.');
                return (new PostResource(false, 'order_id missing', null))->response()->setStatusCode(400);
            }

            $orderId = $data['order_id'];
            $status = $data['transaction_status'] ?? null;
            $fraud = $data['fraud_status'] ?? null;
            $transactionId = $data['transaction_id'] ?? null;

            Log::info('Parsed notification', [
                'order_id' => $orderId,
                'status' => $status,
                'fraud' => $fraud,
                'transaction_id' => $transactionId,
            ]);

            $enrollment = CourseEnrollment::where('midtrans_order_id', $orderId)->first();
            if ($enrollment) {
                $updateData = [
                    'payment_status' => $status === 'settlement' ? 'paid' : ($status === 'expire' ? 'expired' : 'pending'),
                    'transaction_status' => $status,
                    'fraud_status' => $fraud,
                    'midtrans_transaction_id' => $transactionId,
                ];

                if ($status === 'settlement') {
                    $updateData['enrolled_at'] = now();
                    $updateData['paid_at'] = now();

                    // Jika ada coupon, catat penggunaan coupon
                    if ($enrollment->coupon_id) {
                        CouponUsage::firstOrCreate([
                            'user_id' => $enrollment->user_id,
                            'course_id' => $enrollment->course_id,
                            'coupon_id' => $enrollment->coupon_id,
                        ], [
                            'used_at' => now(),
                        ]);

                        // Tambah used_count di coupon
                        Coupon::where('id', $enrollment->coupon_id)->increment('used_count');
                    }
                }

                $enrollment->update($updateData);

                return new PostResource(true, 'OK', null);
            }

            Log::warning('Order ID not found: ' . $orderId);
            return (new PostResource(false, 'Not Found', null))->response()->setStatusCode(404);
        } catch (\Exception $e) {
            Log::error('Midtrans callback error: ' . $e->getMessage());
            return (new PostResource(false, 'Error', null))->response()->setStatusCode(500);
        }
    }
}
