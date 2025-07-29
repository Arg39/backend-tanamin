<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorResource extends JsonResource
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
            'name' => trim($this->first_name . ' ' . $this->last_name),
            'course_held' => $this->courses()->where('status', 'published')->count(),
            'photo_profile' => $this->photo_profile ? asset('storage/' . $this->photo_profile) : null,
            'expertise' => $this->detail->expertise ?? null,
        ];
    }
}
