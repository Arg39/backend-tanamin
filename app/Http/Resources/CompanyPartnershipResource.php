<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CompanyPartnershipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_name' => $this->partner_name,
            'logo' => $this->logo ? Storage::disk('public')->url($this->logo) : null,
            'website_url' => $this->website_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}