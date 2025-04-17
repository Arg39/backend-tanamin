<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Status of the response (true for success, false for failure).
     *
     * @var bool
     */
    public $status;

    /**
     * Message to include in the response.
     *
     * @var string
     */
    public $message;

    /**
     * Create a new resource instance.
     *
     * @param  bool   $status   Indicates success (true) or failure (false).
     * @param  string $message  Message to include in the response.
     * @param  mixed  $resource The resource data.
     * @return void
     */
    public function __construct(bool $status, string $message, $resource)
    {
        parent::__construct($resource);
        $this->status  = $status;
        $this->message = $message;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'status'  => $this->status,
            'message' => $this->message,
            'data'    => $this->resource,
        ];
    }
}
