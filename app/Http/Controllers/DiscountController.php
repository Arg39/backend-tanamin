<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CourseDiscount;
use App\Http\Resources\TableResource;
use App\Http\Resources\DiscountResource;
use App\Http\Resources\PostResource;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $discounts = CourseDiscount::orderBy('created_at', 'desc')->paginate($request->get('per_page', 10));
        $resource = [
            'data' => DiscountResource::collection($discounts)
        ];
        return (new TableResource(true, 'List of discounts', $resource))->response();
    }

    public function show($id)
    {
        $discount = CourseDiscount::find($id);
        if (!$discount) {
            return (new PostResource(false, 'Discount not found', null))->response();
        }
        return (new PostResource(true, 'Discount detail', (new DiscountResource($discount))->resolve(request())))->response();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'type' => 'required|in:percent,nominal',
            'value' => 'required|integer|min:1',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after_or_equal:start_at',
        ]);
    
        $discount = CourseDiscount::create([
            'id' => Str::uuid(),
            'title' => $validated['title'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'start_at' => Carbon::parse($validated['start_at']),
            'end_at' => Carbon::parse($validated['end_at']),
            'is_active' => true,
        ]);
    
        return (new PostResource(true, 'Discount created', (new DiscountResource($discount))->resolve(request())))->response();
    }

    public function update(Request $request, $id)
    {
        $discount = CourseDiscount::find($id);
        if (!$discount) {
            return (new PostResource(false, 'Discount not found', null))->response();
        }

        $validated = $request->validate([
            'title' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|required|in:percent,nominal',
            'value' => 'sometimes|required|integer|min:1',
            'start_at' => 'sometimes|required|date',
            'end_at' => 'sometimes|required|date|after_or_equal:start_at',
            'is_active' => 'boolean'
        ]);

        $discount->update($validated);

        return (new PostResource(true, 'Discount updated', (new DiscountResource($discount))->resolve(request())))->response();
    }

    public function destroy($id)
    {
        $discount = CourseDiscount::find($id);
        if (!$discount) {
            return (new PostResource(false, 'Discount not found', null))->response();
        }

        $discount->delete();

        return (new PostResource(true, 'Discount deleted', null))->response();
    }
}