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

        // Helper to get full URL for storage files
        $storageUrl = function ($path) {
            return $path ? url('storage/' . ltrim($path, '/')) : null;
        };

        $data = [
            'id' => $this->id,
            'role' => $this->role,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'status' => $this->status,
            'photo_profile' => $storageUrl($this->photo_profile),
            'created_at' => $this->created_at,
            'detail' => [
                'expertise' => $detail->expertise ?? null,
                'about' => $detail->about ?? null,
                'social_media' => $detail->social_media ?? null,
                'photo_cover' => $storageUrl($detail->photo_cover ?? null),
                'update_password' => $detail->update_password ?? null,
            ],
        ];

        if ($this->role === 'instructor') {
            $data['total_courses'] = $this->courses ? $this->courses->count() : 0;
        }

        return $data;
    }
}