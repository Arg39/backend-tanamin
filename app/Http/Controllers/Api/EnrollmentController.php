<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseCheckoutSession;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\CourseAttribute;
use Carbon\Carbon;

class EnrollmentController extends Controller
{
    public function buyNow(Request $request, $courseId)
    {
        try {
            $user = $request->user();
            $course = Course::findOrFail($courseId);

            // Cek kalau user sudah pernah beli
            $alreadyEnrolled = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->whereHas('checkoutSession', function ($q) {
                    $q->where('payment_status', 'paid');
                })
                ->exists();

            if ($alreadyEnrolled) {
                return (new PostResource(false, 'Kursus ini sudah dibeli.', null))->response()->setStatusCode(400);
            }

            // --- Hitung harga dasar & diskon ---
            $basePrice = $course->price ?? 0;
            $discount = 0;

            if ($course->is_discount_active) {
                $isDiscountActive = $course->discount_start_at && $course->discount_end_at
                    ? now()->between($course->discount_start_at, $course->discount_end_at)
                    : true;

                if ($isDiscountActive) {
                    if ($course->discount_type === 'percent') {
                        $discount = intval($basePrice * $course->discount_value / 100);
                    } elseif ($course->discount_type === 'nominal') {
                        $discount = $course->discount_value;
                    }
                }
            }

            $priceAfterDiscount = max(0, $basePrice - $discount);

            // --- Cek kupon ---
            $couponUsage = CouponUsage::where('user_id', $user->id)
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
                    if ($coupon->max_usage !== null && $coupon->used_count >= $coupon->max_usage) {
                        return (new PostResource(false, 'Kupon sudah mencapai batas penggunaan.', null))->response()->setStatusCode(400);
                    }

                    $couponDiscount = $coupon->type === 'percent'
                        ? intval($priceAfterDiscount * $coupon->value / 100)
                        : $coupon->value;

                    $couponId = $coupon->id;
                }
            }

            $finalPrice = max(0, $priceAfterDiscount - $couponDiscount);

            // Hitung PPN 12%
            $ppn = $finalPrice > 0 ? intval(round($finalPrice * 0.12)) : 0;
            $totalWithPpn = $finalPrice + $ppn;

            // --- Kursus gratis langsung aktif ---
            if ($finalPrice <= 0) {
                // Cek apakah sudah ada enrollment inactive (pending) untuk user & course
                $existingEnrollment = CourseEnrollment::where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->first();

                if ($existingEnrollment) {
                    // Update ke status active dan buat session baru jika perlu
                    $checkoutSession = CourseCheckoutSession::create([
                        'user_id' => $user->id,
                        'checkout_type' => 'direct',
                        'payment_status' => 'paid',
                        'payment_type' => 'free',
                        'paid_at' => now(),
                    ]);
                    $existingEnrollment->update([
                        'checkout_session_id' => $checkoutSession->id,
                        'coupon_id' => $couponId,
                        'price' => 0,
                        'payment_type' => 'free',
                        'access_status' => 'active',
                    ]);
                    if ($couponId && !$couponUsage) {
                        CouponUsage::firstOrCreate([
                            'user_id' => $user->id,
                            'course_id' => $course->id,
                            'coupon_id' => $couponId,
                        ], ['used_at' => now()]);
                        Coupon::where('id', $couponId)->increment('used_count');
                    }
                    return new PostResource(true, 'Kursus berhasil diakses secara gratis.', [
                        'enrollment_id' => $existingEnrollment->id,
                    ]);
                } else {
                    $checkoutSession = CourseCheckoutSession::create([
                        'user_id' => $user->id,
                        'checkout_type' => 'direct',
                        'payment_status' => 'paid',
                        'payment_type' => 'free',
                        'paid_at' => now(),
                    ]);
                    $enrollment = CourseEnrollment::create([
                        'checkout_session_id' => $checkoutSession->id,
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'coupon_id' => $couponId,
                        'price' => 0,
                        'payment_type' => 'free',
                        'access_status' => 'active',
                    ]);
                    if ($couponId && !$couponUsage) {
                        CouponUsage::firstOrCreate([
                            'user_id' => $user->id,
                            'course_id' => $course->id,
                            'coupon_id' => $couponId,
                        ], ['used_at' => now()]);
                        Coupon::where('id', $couponId)->increment('used_count');
                    }
                    return new PostResource(true, 'Kursus berhasil diakses secara gratis.', [
                        'enrollment_id' => $enrollment->id,
                    ]);
                }
            }

            // --- Cek apakah ada transaksi pending sebelumnya ---
            $pendingSession = CourseCheckoutSession::where('user_id', $user->id)
                ->where('checkout_type', 'direct')
                ->where('payment_status', 'pending')
                ->whereHas('enrollments', function ($q) use ($course) {
                    $q->where('course_id', $course->id);
                })
                ->latest()
                ->first();

            if ($pendingSession) {
                $orderId = $pendingSession->midtrans_order_id;

                try {
                    $midtransStatus = \Midtrans\Transaction::status($orderId);
                    $status = $midtransStatus->transaction_status ?? null;

                    if (in_array($status, ['expire', 'cancel', 'deny'])) {
                        // kalau sudah tidak berlaku → update jadi expired
                        $pendingSession->update([
                            'payment_status' => 'expired',
                            'transaction_status' => $status,
                        ]);
                    } elseif ($status === null) {
                        // Jika status null (tidak ditemukan di Midtrans), update session dengan order_id baru
                        $orderId = 'ORD-' . now()->format('ymdHis') . '-' . Str::random(8);

                        $midtrans = MidtransService::createTransaction([
                            'transaction_details' => [
                                'order_id' => $orderId,
                                'gross_amount' => (int)$totalWithPpn,
                            ],
                            'customer_details' => [
                                'first_name' => $user->name,
                                'email' => $user->email,
                            ],
                        ]);

                        $pendingSession->update([
                            'midtrans_order_id' => $orderId,
                        ]);

                        return new PostResource(true, 'Silakan lanjutkan pembayaran.', [
                            'redirect_url' => $midtrans->redirect_url,
                        ]);
                    } else {
                        // masih pending → kembalikan redirect_url lama
                        return new PostResource(true, 'Silakan lanjutkan pembayaran.', [
                            'redirect_url' => $midtransStatus->redirect_url ?? null,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Jika gagal cek status (misal order_id tidak ditemukan di Midtrans), buat order baru dan update session
                    $orderId = 'ORD-' . now()->format('ymdHis') . '-' . Str::random(8);

                    $midtrans = MidtransService::createTransaction([
                        'transaction_details' => [
                            'order_id' => $orderId,
                            'gross_amount' => (int)$totalWithPpn,
                        ],
                        'customer_details' => [
                            'first_name' => $user->name,
                            'email' => $user->email,
                        ],
                    ]);

                    $pendingSession->update([
                        'midtrans_order_id' => $orderId,
                    ]);

                    return new PostResource(true, 'Silakan lanjutkan pembayaran.', [
                        'redirect_url' => $midtrans->redirect_url,
                    ]);
                }
            }

            // --- Buat order baru ---
            $orderId = 'ORD-' . now()->format('ymdHis') . '-' . Str::random(8);

            $midtrans = MidtransService::createTransaction([
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int)$totalWithPpn,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

            $checkoutSession = CourseCheckoutSession::create([
                'user_id' => $user->id,
                'checkout_type' => 'direct',
                'payment_status' => 'pending',
                'midtrans_order_id' => $orderId,
                'payment_type' => 'midtrans',
            ]);

            // Cek apakah sudah ada enrollment inactive (pending) untuk user & course
            $existingEnrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if ($existingEnrollment) {
                // Update ke session baru dan set status inactive
                $existingEnrollment->update([
                    'checkout_session_id' => $checkoutSession->id,
                    'coupon_id' => $couponId,
                    'price' => $finalPrice,
                    'payment_type' => 'midtrans',
                    'access_status' => 'inactive',
                ]);
            } else {
                CourseEnrollment::create([
                    'checkout_session_id' => $checkoutSession->id,
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'coupon_id' => $couponId,
                    'price' => $finalPrice,
                    'payment_type' => 'midtrans',
                    'access_status' => 'inactive',
                ]);
            }

            return new PostResource(true, 'Silakan lanjutkan pembayaran.', [
                'redirect_url' => $midtrans->redirect_url,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return (new PostResource(false, 'Validasi gagal', $e->errors()))->response()->setStatusCode(422);
        } catch (\Exception $e) {
            $message = 'Terjadi kesalahan pada server.';
            if (app()->environment(['local', 'development'])) {
                $message .= ' ' . $e->getMessage();
            }
            return (new PostResource(false, $message, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]))->response()->setStatusCode(500);
        }
    }

    public function midtransCallback(Request $request)
    {
        Log::info('Midtrans callback hit', ['payload' => $request->all()]);

        try {
            $data = $request->all();

            if (empty($data['order_id'])) {
                Log::warning('Callback received without order_id.');
                return response()->json(['status' => false, 'message' => 'order_id missing'], 200);
            }

            $orderId       = $data['order_id'];
            $status        = $data['transaction_status'] ?? null;
            $fraud         = $data['fraud_status'] ?? null;
            $transactionId = $data['transaction_id'] ?? null;

            Log::info('Parsed notification', [
                'order_id'       => $orderId,
                'status'         => $status,
                'fraud'          => $fraud,
                'transaction_id' => $transactionId,
            ]);

            $checkoutSession = CourseCheckoutSession::where('midtrans_order_id', $orderId)->first();
            if (!$checkoutSession) {
                Log::warning('Order ID not found: ' . $orderId);
                return response()->json(['status' => false, 'message' => 'Not Found'], 200);
            }

            $paymentStatus = 'pending';

            switch ($status) {
                case 'settlement':
                    $paymentStatus = 'paid';
                    break;

                case 'capture':
                    if ($fraud === 'accept') {
                        $paymentStatus = 'paid';
                    } elseif ($fraud === 'challenge') {
                        $paymentStatus = 'pending';
                    } else {
                        $paymentStatus = 'expired';
                    }
                    break;

                case 'cancel':
                case 'deny':
                case 'expire':
                    $paymentStatus = 'expired';
                    break;

                default:
                    $paymentStatus = 'pending';
            }

            $updateData = [
                'payment_status'        => $paymentStatus,
                'transaction_status'    => $status,
                'fraud_status'          => $fraud,
                'midtrans_transaction_id' => $transactionId,
            ];

            if ($paymentStatus === 'paid') {
                $updateData['paid_at'] = now();

                // Update semua enrollments pada session ini (baik direct maupun cart)
                foreach ($checkoutSession->enrollments as $enrollment) {
                    $enrollment->access_status = 'active';
                    $enrollment->save();

                    if ($enrollment->coupon_id) {
                        CouponUsage::firstOrCreate(
                            [
                                'user_id'   => $enrollment->user_id,
                                'course_id' => $enrollment->course_id,
                                'coupon_id' => $enrollment->coupon_id,
                            ],
                            [
                                'used_at' => now(),
                            ]
                        );

                        Coupon::where('id', $enrollment->coupon_id)->increment('used_count');
                    }

                    // Kirim notifikasi ke user
                    try {
                        $notificationController = app(NotificationController::class);
                        $notificationController->makeNotification(
                            $enrollment->user_id,
                            'Pembayaran Berhasil',
                            'Pembayaran kursus "' . ($enrollment->course->title ?? '-') . '" berhasil. Anda sudah dapat mengakses kursus. Terima kasih telah belajar di Tanamin Kursus!'
                        );
                    } catch (\Exception $e) {
                        Log::error('Gagal mengirim notifikasi pembayaran: ' . $e->getMessage());
                    }
                }
            } elseif ($paymentStatus === 'expired') {
                // Jika expired, pastikan semua enrollment access_status tetap 'inactive'
                foreach ($checkoutSession->enrollments as $enrollment) {
                    $enrollment->access_status = 'inactive';
                    $enrollment->save();
                }
            }

            $checkoutSession->update($updateData);

            return response()->json(['status' => true, 'message' => 'OK'], 200);
        } catch (\Exception $e) {
            Log::error('Midtrans callback error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error'], 200);
        }
    }

    public function latestTransactions(Request $request)
    {
        try {
            $allowedSortBy = ['created_at', 'payment_status'];
            $sortBy = $request->get('sortBy', 'created_at');
            $sortOrder = strtolower($request->get('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';

            if (!in_array($sortBy, $allowedSortBy)) {
                $sortBy = 'created_at';
            }

            $perPage = (int) $request->get('perPage', 10);
            $page = (int) $request->get('page', 1);

            $userSearch = $request->get('user');

            $query = CourseCheckoutSession::with([
                'user:id,first_name,last_name',
                'enrollments.course:id,title'
            ])->select([
                'id',
                'user_id',
                'payment_status',
                'created_at',
                'payment_type'
            ]);

            if ($userSearch) {
                $query->whereHas('user', function ($q) use ($userSearch) {
                    $searchTerm = '%' . strtolower($userSearch) . '%';
                    $q->whereRaw("LOWER(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(first_name) LIKE ?", [$searchTerm])
                        ->orWhereRaw("LOWER(last_name) LIKE ?", [$searchTerm]);
                });
            }

            $query->orderBy($sortBy, $sortOrder);

            $sessions = $query->paginate($perPage, ['*'], 'page', $page);

            Carbon::setLocale('id');

            $sessions->getCollection()->transform(function ($session) {
                $userName = $session->user ? ($session->user->full_name ?? trim($session->user->first_name . ' ' . $session->user->last_name)) : null;
                $courses = $session->enrollments->map(function ($enrollment) {
                    return $enrollment->course ? $enrollment->course->title : null;
                })->filter()->values()->all();

                return [
                    'id' => $session->id,
                    'user' => $userName,
                    'courses' => $courses,
                    'created_at' => $session->created_at ? Carbon::parse($session->created_at)->translatedFormat('d F Y') : null,
                    'payment_status' => $session->payment_status,
                    'payment_type' => $session->payment_type,
                ];
            });

            return new TableResource(true, 'Latest transactions retrieved successfully', [
                'data' => $sessions,
            ], 200);
        } catch (\Exception $e) {
            return (new ErrorResource(['message' => 'Failed to retrieve transactions: ' . $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }

    public function checkoutCart(Request $request)
    {
        try {
            $user = $request->user();

            // Ambil session cart aktif user
            $cartSession = \App\Models\CourseCheckoutSession::where('user_id', $user->id)
                ->where('checkout_type', 'cart')
                ->where('payment_status', 'pending')
                ->with('enrollments.course')
                ->first();

            if (!$cartSession || $cartSession->enrollments->isEmpty()) {
                return (new PostResource(false, 'Cart kosong.', null))->response()->setStatusCode(400);
            }

            $totalPrice = 0;
            $enrollmentsToPay = [];
            $enrollmentsFree = [];

            // Hitung total harga & kelompokkan enrollment
            foreach ($cartSession->enrollments as $enrollment) {
                $course = $enrollment->course;
                if (!$course) continue;

                // Hitung harga & diskon
                $basePrice = $course->price ?? 0;
                $discount = 0;
                if ($course->is_discount_active) {
                    $isDiscountActive = $course->discount_start_at && $course->discount_end_at
                        ? now()->between($course->discount_start_at, $course->discount_end_at)
                        : true;
                    if ($isDiscountActive) {
                        if ($course->discount_type === 'percent') {
                            $discount = intval($basePrice * $course->discount_value / 100);
                        } elseif ($course->discount_type === 'nominal') {
                            $discount = $course->discount_value;
                        }
                    }
                }
                $finalPrice = max(0, $basePrice - $discount);

                // Update enrollment price & payment_type
                $enrollment->price = $finalPrice;
                $enrollment->payment_type = $finalPrice > 0 ? 'midtrans' : 'free';
                $enrollment->save();

                if ($finalPrice > 0) {
                    $totalPrice += $finalPrice;
                    $enrollmentsToPay[] = $enrollment;
                } else {
                    $enrollmentsFree[] = $enrollment;
                }
            }

            // Hitung PPN 12% jika ada yang berbayar
            $ppn = $totalPrice > 0 ? intval(round($totalPrice * 0.12)) : 0;
            $totalWithPpn = $totalPrice + $ppn;

            // Proses course gratis: langsung aktifkan
            foreach ($enrollmentsFree as $enrollment) {
                $enrollment->access_status = 'active';
                $enrollment->payment_type = 'free';
                $enrollment->save();
            }

            // Jika semua course gratis
            if (empty($enrollmentsToPay)) {
                $cartSession->update([
                    'payment_status' => 'paid',
                    'payment_type' => 'free',
                    'paid_at' => now(),
                ]);
                return new PostResource(true, 'Semua kursus gratis berhasil diakses.', [
                    'enrollment_ids' => $cartSession->enrollments->pluck('id'),
                ]);
            }

            // Buat order Midtrans untuk course berbayar
            $orderId = 'CART-' . now()->format('ymdHis') . '-' . \Illuminate\Support\Str::random(8);

            $midtrans = \App\Services\MidtransService::createTransaction([
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int)$totalWithPpn,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

            // Update session cart
            $cartSession->update([
                'midtrans_order_id' => $orderId,
                'payment_type' => 'midtrans',
            ]);

            // Pastikan enrollmentsToPay access_status tetap inactive, payment_type midtrans
            foreach ($enrollmentsToPay as $enrollment) {
                $enrollment->access_status = 'inactive';
                $enrollment->payment_type = 'midtrans';
                $enrollment->save();
            }

            return new PostResource(true, 'Silakan lanjutkan pembayaran.', [
                'redirect_url' => $midtrans->redirect_url,
            ]);
        } catch (\Exception $e) {
            $message = 'Terjadi kesalahan pada server.';
            if (app()->environment(['local', 'development'])) {
                $message .= ' ' . $e->getMessage();
            }
            return (new PostResource(false, $message, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]))->response()->setStatusCode(500);
        }
    }
}
