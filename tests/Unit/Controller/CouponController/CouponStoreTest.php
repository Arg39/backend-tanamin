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

class CouponStoreTest extends TestCase
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

    public function test_store_creates_coupon_with_valid_payload()
    {
        $code = 'CPN_' . Str::random(8);
        $payload = [
            'title' => 'Test Coupon',
            'code' => $code,
            'type' => 'percent',
            'value' => 15,
            'start_at' => Carbon::now()->subDay()->toDateTimeString(),
            'end_at' => Carbon::now()->addDay()->toDateTimeString(),
            'is_active' => true,
            'max_usage' => null,
        ];

        $controller = new CouponController();
        $request = new Request($payload);
        $response = $controller->store($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true on successful create');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Coupon created', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // resource may return array or object; normalize
        $it = is_object($data) ? (array) $data : $data;
        $this->assertIsArray($it);
        $this->assertArrayHasKey('code', $it);
        $this->assertArrayHasKey('title', $it);
        $this->assertEquals($payload['code'], $it['code']);
        $this->assertEquals($payload['title'], $it['title']);

        $this->assertDatabaseHas('coupons', [
            'code' => $payload['code'],
            'title' => $payload['title'],
        ]);
    }
}