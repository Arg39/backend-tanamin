<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyProfileDetailResource extends JsonResource
{
    public $profile;
    public $statistics;

    public function __construct($data)
    {
        $this->profile = $data['profile'];
        $this->statistics = $data['statistics'];
        parent::__construct($data);
    }
    
    public function toArray($request)
    {
        return [
            'about' => $this->profile->about ?? null,
            'vision' => $this->profile->vision ?? null,
            'mission' => $this->profile->mission ?? [],
            'statistics' => $this->statistics->map(function ($stat) {
                return [
                    'title' => $stat->title,
                    'value' => $stat->value,
                    'unit' => $stat->unit,
                ];
            }),
        ];
    }
}