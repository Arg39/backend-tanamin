<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardCourseResource extends JsonResource
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
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'title' => $this->title,
            'instructor' => $this->instructor 
                ? trim($this->instructor->first_name . ' ' . $this->instructor->last_name)
                : null,
            'total_material' => $this->getTotalMaterialsAttribute(),
            'total_quiz' => $this->getTotalQuizAttribute(),
            'price' => $this->price,
        ];
    }
}
