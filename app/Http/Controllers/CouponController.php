<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Http\Resources\TableResource;
use App\Http\Resources\CouponResource;
use App\Http\Resources\PostResource;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $coupons = Coupon::query()->paginate($perPage);

        // Wrap each item with CouponResource
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
}