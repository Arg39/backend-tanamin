<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'id_category' => $this->id_category,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'title' => $this->title,
            'instructor' => $this->instructor,
            'total_material' => $this->total_material,
            'price' => $this->price,
            'discount' => $this->discounts->first(),
        ];
    }
}
