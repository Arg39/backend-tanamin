<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Status of the response (true for success, false for failed).
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
     * @param  bool   $status   Indicates success (true) or failed (false).
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
        if (is_null($this->resource)) {
            return [
                'status'  => $this->status ? 'success' : 'failed',
                'message' => $this->message,
            ];
        }

        $data = parent::toArray($request);

        if (isset($data['detail']) && isset($data['detail']['user_id'])) {
            unset($data['detail']['user_id']);
        }

        return [
            'status'  => $this->status ? 'success' : 'failed',
            'message' => $this->message,
            'data'    => $data,
        ];
    }

    /**
     * Remove "data" wrapping for failed/null resource responses.
     */
    public function withResponse($request, $response)
    {
        if (is_null($this->resource)) {
            $response->setData((object) [
                'status'  => $this->status ? 'success' : 'failed',
                'message' => $this->message,
            ]);
        }
    }
}
