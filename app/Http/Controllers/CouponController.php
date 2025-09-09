<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Http\Resources\TableResource;
use App\Http\Resources\CouponResource;
use App\Http\Resources\PostResource;
use App\Models\CouponUsage;
use App\Models\Course;
use Carbon\Carbon;
use Tymon\JWTAuth\Contracts\Providers\JWT;
use Tymon\JWTAuth\Facades\JWTAuth;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $coupons = Coupon::query()->paginate($perPage);

        $coupons->getCollection()->transform(function ($coupon) use ($request) {
            return (new CouponResource($coupon))->resolve($request);
        });

        return new TableResource(true, 'List coupon', ['data' => $coupons], 200);
    }

    public function show($id, Request $request)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return new PostResource(false, 'Coupon not found', null);
        }

        return new PostResource(true, 'Coupon detail', (new CouponResource($coupon))->resolve($request));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:percent,nominal',
            'value' => 'required|integer',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after_or_equal:start_at',
            'is_active' => 'boolean',
            'max_usage' => 'nullable|integer',
        ]);

        $coupon = Coupon::create($validated);

        return new PostResource(true, 'Coupon created', (new CouponResource($coupon))->resolve($request));
    }

    public function update($id, Request $request)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return new PostResource(false, 'Coupon not found', null);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:coupons,code,' . $id,
            'type' => 'sometimes|in:percent,nominal',
            'value' => 'sometimes|integer',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date|after_or_equal:start_at',
            'is_active' => 'sometimes|boolean',
            'max_usage' => 'nullable|integer',
        ]);

        $coupon->update($validated);

        return new PostResource(true, 'Coupon updated', (new CouponResource($coupon))->resolve($request));
    }

    public function destroy($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return new PostResource(false, 'Coupon not found', null);
        }

        $coupon->delete();

        return new PostResource(true, 'Coupon deleted', null);
    }

    public function useCoupon(Request $request, $courseId)
    {
        try {
            $userId = JWTAuth::user()->id;

            $course = Course::find($courseId);
            if (!$course) {
                return new PostResource(false, 'Course not found', null);
            }

            $validated = $request->validate([
                'coupon_code' => 'required|string|exists:coupons,code',
            ]);

            // find coupon by code
            $coupon = Coupon::where('code', $validated['coupon_code'])->first();

            if (!$coupon) {
                return new PostResource(false, 'Coupon not found', null);
            }

            if (!$coupon->is_active) {
                return new PostResource(false, 'Coupon is not active', null);
            }

            if (!is_null($coupon->max_usage) && $coupon->used_count >= $coupon->max_usage) {
                return new PostResource(false, 'Coupon usage limit reached', null);
            }

            // Pakai timezone Kalimantan (WITA)
            $now = Carbon::now('Asia/Makassar');
            $startAt = $coupon->start_at->timezone('Asia/Makassar')->startOfDay();
            $endAt   = $coupon->end_at->timezone('Asia/Makassar')->endOfDay();

            if (!$now->between($startAt, $endAt)) {
                return new PostResource(false, 'Coupon is not valid at this time', null);
            }

            if (CouponUsage::hasUserUsedCoupon($userId, $courseId, $coupon->id)) {
                return new PostResource(false, 'You have already used this coupon', null);
            }

            try {
                CouponUsage::create([
                    'user_id' => $userId,
                    'course_id' => $courseId,
                    'coupon_id' => $coupon->id,
                    'used_at' => now(),
                ]);

                return new PostResource(true, 'Coupon applied successfully', null);
            } catch (\Exception $e) {
                return new PostResource(false, 'Failed to record coupon usage: ' . $e->getMessage(), null);
            }
        } catch (\Exception $e) {
            return new PostResource(false, 'Error validating coupon: ' . $e->getMessage(), null);
        }
    }
}
