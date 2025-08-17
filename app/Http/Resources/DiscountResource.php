<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        // Set Carbon locale to Indonesian
        Carbon::setLocale('id');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'value' => $this->type === 'percent'
                ? $this->value . '%'
                : 'Rp. ' . $this->value,
            'start_at' => Carbon::parse($this->start_at)->translatedFormat('d F Y'),
            'end_at' => Carbon::parse($this->end_at)->translatedFormat('d F Y'),
            'is_active' => $this->is_active,
        ];
    }
}
