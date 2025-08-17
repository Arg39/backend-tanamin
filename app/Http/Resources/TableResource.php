<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
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
     * HTTP status code of the response.
     *
     * @var int
     */
    public $httpCode;

    /**
     * Create a new resource instance.
     *
     * @param  bool   $status   Indicates success (true) or failed (false).
     * @param  string $message  Message to include in the response.
     * @param  mixed  $resource The resource data.
     * @param  int    $httpCode HTTP status code.
     * @return void
     */
    public function __construct(bool $status, string $message, $resource, int $httpCode = 200)
    {
        parent::__construct($resource);
        $this->status   = $status;
        $this->message  = $message;
        $this->httpCode = $httpCode;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $data = $this->status ? [
            'items'      => $this->resource['data']->items(),
            'pagination' => [
                'current_page' => $this->resource['data']->currentPage(),
                'last_page'    => $this->resource['data']->lastPage(),
                'total'        => $this->resource['data']->total(),
            ],
        ] : null;

        return [
            'status'   => $this->status ? 'success' : 'failed',
            'message'  => $this->message,
            'httpCode' => $this->httpCode,
            'data'     => $data,
        ];
    }
}
