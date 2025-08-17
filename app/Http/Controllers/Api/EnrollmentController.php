<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseCheckoutSession;
use App\Models\CourseEnrollment;
use App\Models\CheckoutSessionItem;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Notification;

class EnrollmentController extends Controller
{
    public function buyNow(Request $request)
    {
        $user = $request->user();
        $course = Course::findOrFail($request->course_id);

        // Cek apakah sudah pernah dibeli
        $alreadyEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('payment_status', 'paid')
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json(['message' => 'Kursus ini sudah dibeli.'], 400);
        }

        // Cek apakah sudah ada enrollment pending untuk user dan course ini
        $pendingEnrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('payment_status', 'pending')
            ->first();

        if ($pendingEnrollment) {
            // Ambil order_id dari enrollment yang sudah ada
            $orderId = $pendingEnrollment->midtrans_order_id;
            // Buat ulang link Midtrans (jika perlu, atau simpan link saat pertama kali create)
            $midtrans = MidtransService::createTransaction([
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int)$course->price,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

            return response()->json([
                'redirect_url' => $midtrans->redirect_url,
            ]);
        }

        // Buat transaksi Midtrans baru
        $orderId = 'ORDER-' . strtoupper(uniqid());
        $midtrans = MidtransService::createTransaction([
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int)$course->price,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
        ]);

        // Simpan enrollment dengan status pending
        CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => $course->price,
            'payment_type' => 'midtrans',
            'payment_status' => 'pending',
            'midtrans_order_id' => $orderId,
        ]);

        return response()->json([
            'redirect_url' => $midtrans->redirect_url,
        ]);
    }

    public function midtransCallback(Request $request)
    {
        Log::info('Midtrans callback hit', ['payload' => $request->all()]);

        try {
            $data = $request->all();

            // Validasi order_id
            if (empty($data['order_id'])) {
                Log::warning('Callback received without order_id.');
                return response()->json(['message' => 'order_id missing'], 400);
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

            // 🔍 Cek di course_enrollments
            $enrollment = CourseEnrollment::where('midtrans_order_id', $orderId)->first();
            if ($enrollment) {
                $enrollment->update([
                    'payment_status' => $status === 'settlement' ? 'paid' : ($status === 'expire' ? 'expired' : 'pending'),
                    'transaction_status' => $status,
                    'fraud_status' => $fraud,
                    'midtrans_transaction_id' => $transactionId,
                    'enrolled_at' => $status === 'settlement' ? now() : null,
                ]);

                return response()->json(['message' => 'OK']);
            }

            // 🔍 Cek di checkout_sessions
            $session = CourseCheckoutSession::with('items')->where('midtrans_order_id', $orderId)->first();
            if ($session) {
                $session->update([
                    'payment_status' => $status === 'settlement' ? 'paid' : ($status === 'expire' ? 'expired' : 'pending'),
                    'transaction_status' => $status,
                    'fraud_status' => $fraud,
                    'midtrans_transaction_id' => $transactionId,
                    'paid_at' => $status === 'settlement' ? now() : null,
                ]);

                if ($status === 'settlement') {
                    foreach ($session->items as $item) {
                        CourseEnrollment::create([
                            'user_id' => $session->user_id,
                            'course_id' => $item->course_id,
                            'price' => $item->price,
                            'payment_type' => 'midtrans',
                            'payment_status' => 'paid',
                            'enrolled_at' => now(),
                        ]);
                    }
                }

                return response()->json(['message' => 'OK']);
            }

            Log::warning('Order ID not found: ' . $orderId);
            return response()->json(['message' => 'Not Found'], 404);
        } catch (\Exception $e) {
            Log::error('Midtrans callback error: ' . $e->getMessage());
            return response()->json(['message' => 'Error'], 500);
        }
    }
}
