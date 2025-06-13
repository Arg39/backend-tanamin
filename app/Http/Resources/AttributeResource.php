<?php

namespace App\Http\Resources;

use App\Traits\DateFormatTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
{
    use DateFormatTrait;
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
            'updated_at' => $this->dateFormat($this->updated_at),
            'created_at' => $this->dateFormat($this->created_at),
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
