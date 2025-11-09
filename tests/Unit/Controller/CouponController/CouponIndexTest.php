<?php

namespace Tests\Unit\Controller\CouponController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Coupon;
use App\Http\Controllers\Api\CouponController;

class CouponIndexTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Normalize controller/resource response to array.
     */
    private function resolveResponseData($response, Request $request)
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        if (is_object($response) && method_exists($response, 'toResponse')) {
            $httpResponse = $response->toResponse($request);
            if ($httpResponse instanceof JsonResponse) {
                return $httpResponse->getData(true);
            }
            if (method_exists($httpResponse, 'getData')) {
                return $httpResponse->getData(true);
            }
        }

        if (is_object($response) && method_exists($response, 'getData')) {
            return $response->getData(true);
        }

        throw new \RuntimeException('Unable to resolve response data in test. Response type: ' . gettype($response));
    }

    public function test_index_returns_paginated_list_of_coupons()
    {
        // create 3 coupons
        $codes = [];
        for ($i = 0; $i < 3; $i++) {
            $code = 'CPN_' . Str::random(8) . '_' . $i;
            $codes[] = $code;
            Coupon::create([
                'id' => Str::uuid()->toString(),
                'title' => "Coupon {$i}",
                'code' => $code,
                'type' => 'percent',
                'value' => 10,
                'start_at' => Carbon::now()->subDay(),
                'end_at' => Carbon::now()->addDay(),
                'is_active' => true,
                // optional fields if present on model
                'max_usage' => null,
                'used_count' => 0,
            ]);
        }

        $request = new Request();
        $controller = new CouponController();
        $response = $controller->index($request);

        $data = $this->resolveResponseData($response, $request);

        // status
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        // message
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('List coupon', $data['message']);

        // data payload
        $this->assertArrayHasKey('data', $data);
        $returned = $data['data'];

        // Unwrap possible nested structures (TableResource -> ['data' => $paginator], paginator -> ['data'] etc.)
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        // If paginator structure present
        if (is_array($returned) && isset($returned['data'])) {
            $items = $returned['data'];
        } elseif (is_array($returned) && isset($returned['items'])) {
            $items = $returned['items'];
        } elseif (is_array($returned) && array_keys($returned) === range(0, count($returned) - 1)) {
            // already a plain array of items
            $items = $returned;
        } else {
            // try to handle object with toArray
            if (is_object($returned) && method_exists($returned, 'toArray')) {
                $arr = $returned->toArray();
                if (isset($arr['data'])) {
                    $items = $arr['data'];
                } elseif (isset($arr['items'])) {
                    $items = $arr['items'];
                } else {
                    $items = $arr;
                }
            } else {
                // fallback: wrap into array
                $items = is_array($returned) ? $returned : [];
            }
        }

        $this->assertIsArray($items, 'Expected returned coupon items to be an array');
        $this->assertCount(3, $items, 'Expected 3 coupons returned in the index');

        // check that each created code is present in returned items
        $returnedCodes = array_column($items, 'code');
        foreach ($codes as $c) {
            $this->assertContains($c, $returnedCodes);
        }
    }
}