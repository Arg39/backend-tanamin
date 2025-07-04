<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoursePostResource extends JsonResource
{
    protected $extra = [];

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