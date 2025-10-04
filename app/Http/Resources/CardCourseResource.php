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
        $array = [
            'id' => $this->id,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'title' => $this->title,
            'instructor' => $this->instructor
                ? trim($this->instructor->first_name . ' ' . $this->instructor->last_name)
                : null,
            'total_material' => $this->getTotalMaterialsAttribute(),
            'total_quiz' => $this->getTotalQuizAttribute(),
            'price' => $this->price,
            'discount' => $this->active_discount_value,
            'type_discount' => $this->active_discount_type,
            'average_rating' => round($this->avg_rating, 2),
            'total_rating' => $this->total_ratings,
        ];

        // Inject progress jika ada di resource (khusus myCourses)
        if (isset($this->additional['progress'])) {
            $array['progress'] = $this->additional['progress'];
        }

        if (isset($this->additional['owned'])) {
            $array['owned'] = $this->additional['owned'];
        }
        if (isset($this->additional['bookmark'])) {
            $array['bookmark'] = $this->additional['bookmark'];
        }

        return $array;
    }
}
