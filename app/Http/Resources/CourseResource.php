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
            'id_instructor' => $this->id_instructor,
            'title' => $this->title,
            'price' => $this->price,
            'level' => $this->level,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'status' => $this->status,
            'detail' => $this->detail,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'instructor' => $this->instructor ? [
                'id' => $this->instructor->id,
                'first_name' => $this->instructor->first_name,
                'last_name' => $this->instructor->last_name,
                'full_name' => trim($this->instructor->first_name . ' ' . $this->instructor->last_name),
            ] : null,
        ];
    }
}
