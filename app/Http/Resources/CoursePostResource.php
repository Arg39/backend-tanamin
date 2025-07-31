<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoursePostResource extends JsonResource
{
    protected $extra = [];

    /**
     * Format tanggal ke format Indonesia: "30 Juli 2025"
     *
     * @param \Carbon\Carbon|string|null $date
     * @return string|null
     */
    protected function formatIndonesianDate($date)
    {
        if (!$date) return null;
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $dt = \Carbon\Carbon::parse($date);
        $month = $months[(int)$dt->format('m')];
        return $dt->format('d') . ' ' . $month . ' ' . $dt->format('Y');
    }

    /**
     * Tambahkan data ekstra ke resource.
     *
     * @param array $extra
     * @return $this
     */
    public function withExtra(array $extra)
    {
        $this->extra = $extra;
        return $this;
    }

    /**
     * Data utama resource.
     *
     * @return array<string, mixed>
     */
    protected function baseArray()
    {
        $discounted_price = null;
        if ($this->is_discount_active) {
            if ($this->discount_type === 'percent') {
                $discounted_price = $this->price - ($this->price * ($this->discount_value / 100));
            } elseif ($this->discount_type === 'nominal') {
                $discounted_price = $this->price - $this->discount_value;
            }
            // Ensure price doesn't go below zero
            $discounted_price = max($discounted_price, 0);
        }
    
        return [
            'id' => $this->id,
            'id_category' => $this->id_category,
            'id_instructor' => $this->id_instructor,
            'title' => $this->title,
            'price' => $this->price,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_start_at' => $this->formatIndonesianDate($this->discount_start_at),
            'discount_end_at' => $this->formatIndonesianDate($this->discount_end_at),
            'is_discount_active' => $this->is_discount_active,
            'discounted_price' => $discounted_price,
            'level' => $this->level,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'status' => $this->status,
            'detail' => $this->detail,
            'created_at' => $this->formatIndonesianDate($this->created_at),
            'updated_at' => $this->formatIndonesianDate($this->updated_at),
            'category' => optional($this->category)->only(['id', 'name']),
            'instructor' => $this->instructor ? [
                'id' => $this->id_instructor,
                'name' => trim($this->instructor->first_name . ' ' . $this->instructor->last_name),
            ] : null,
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return array_merge(
            $this->baseArray(),
            $this->extra
        );
    }
}