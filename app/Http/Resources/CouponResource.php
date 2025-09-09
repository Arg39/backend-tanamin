<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\CouponUsage;
use Carbon\Carbon;

class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate used_count dynamically from coupon_usages table
        $usedCount = CouponUsage::where('coupon_id', $this->id)->count();

        // Get users who have used this coupon
        $usersUsed = CouponUsage::where('coupon_id', $this->id)
            ->with('user:id,username,first_name,last_name')
            ->get()
            ->map(function ($usage) {
                return [
                    'id' => $usage->user->id,
                    'username' => $usage->user->username,
                    'full_name' => $usage->user->full_name,
                ];
            });

        // Format dates to 'j F Y' in Indonesian
        $startAt = $this->start_at ? Carbon::parse($this->start_at)->locale('id')->isoFormat('D MMMM YYYY') : null;
        $endAt = $this->end_at ? Carbon::parse($this->end_at)->locale('id')->isoFormat('D MMMM YYYY') : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'is_active' => $this->is_active,
            'max_usage' => $this->max_usage,
            'used_count' => $usedCount,
            'users_used' => $usersUsed,
        ];
    }
}
