<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
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
            'content' => $this->content,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
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
