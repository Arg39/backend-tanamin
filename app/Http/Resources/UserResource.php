<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $detail = $this->detail;

        return [
            'id' => $this->id,
            'role' => $this->role,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'status' => $this->status,
            'photo_profile' => $this->photo_profile,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'detail' => [
                'expertise' => $detail->expertise ?? null,
                'about' => $detail->about ?? null,
                'social_media' => $detail->social_media ?? null,
                'photo_cover' => $detail->photo_cover ?? null,
                'update_password' => $detail->update_password ?? null,
            ],
        ];
    }
}