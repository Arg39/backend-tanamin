<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
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

            $alreadyEnrolled = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('payment_status', 'paid')
                ->exists();

            if ($alreadyEnrolled) {
                return (new PostResource(false, 'Kursus ini sudah dibeli.', null))->response()->setStatusCode(400);
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
                    if ($coupon->max_usage !== null && $coupon->used_count >= $coupon->max_usage) {
                        return (new PostResource(false, 'Kupon sudah mencapai batas penggunaan.', null))->response()->setStatusCode(400);
                    }

                    if ($coupon->type === 'percent') {
                        $couponDiscount = intval($priceAfterDiscount * $coupon->value / 100);
                    } else {
                        $couponDiscount = $coupon->value;
                    }
                    $couponId = $coupon->id;
                }
            }

            $finalPrice = max(0, $priceAfterDiscount - $couponDiscount);

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

            $pendingEnrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('payment_status', 'pending')
                ->first();

            if ($pendingEnrollment) {
                $orderId = $pendingEnrollment->midtrans_order_id;

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

            $enrollment = CourseEnrollment::where('midtrans_order_id', $orderId)->first();
            if (!$enrollment) {
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
                $updateData['enrolled_at']   = now();
                $updateData['paid_at']       = now();
                $updateData['access_status'] = 'active';

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
            }

            $enrollment->update($updateData);

            return response()->json(['status' => true, 'message' => 'OK'], 200);
        } catch (\Exception $e) {
            Log::error('Midtrans callback error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error'], 200);
        }
    }

    public function latestTransactions(Request $request)
    {
        try {
            $allowedSortBy = ['enrolled_at', 'payment_status', 'access_status'];
            $sortBy = $request->get('sortBy', 'enrolled_at');
            $sortOrder = strtolower($request->get('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';

            if (!in_array($sortBy, $allowedSortBy)) {
                $sortBy = 'enrolled_at';
            }

            $perPage = (int) $request->get('perPage', 10);
            $page = (int) $request->get('page', 1);

            $userSearch = $request->get('user');

            $query = CourseEnrollment::with([
                'user:id,first_name,last_name',
                'course:id,title'
            ])->select([
                'id',
                'user_id',
                'course_id',
                'enrolled_at',
                'payment_status',
                'access_status',
                'price'
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

            $enrollments = $query->paginate($perPage, ['*'], 'page', $page);

            Carbon::setLocale('id');

            $enrollments->getCollection()->transform(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'user' => $enrollment->user ? ($enrollment->user->full_name ?? trim($enrollment->user->first_name . ' ' . $enrollment->user->last_name)) : null,
                    'course' => $enrollment->course ? $enrollment->course->title : null,
                    'enrolled_at' => $enrollment->enrolled_at ? Carbon::parse($enrollment->enrolled_at)->translatedFormat('d F Y') : null,
                    'payment_status' => $enrollment->payment_status,
                    'access_status' => $enrollment->access_status,
                    'price' => $enrollment->price,
                ];
            });

            return new TableResource(true, 'Latest transactions retrieved successfully', [
                'data' => $enrollments,
            ], 200);
        } catch (\Exception $e) {
            return (new ErrorResource(['message' => 'Failed to retrieve transactions: ' . $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }
}
