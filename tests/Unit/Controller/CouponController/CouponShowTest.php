<?php

namespace Tests\Unit\Controller\CouponController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Coupon;
use App\Http\Controllers\Api\CouponController;

class CouponShowTest extends TestCase
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

    public function test_show_returns_coupon_when_exists()
    {
        $coupon = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Test Coupon Show',
            'code' => 'CPN_SHOW_' . Str::random(6),
            'type' => 'percent',
            'value' => 15,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        $controller = new CouponController();
        $request = new Request();
        $response = $controller->show($coupon->id, $request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true for existing coupon');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Coupon detail', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // data may be object or array; normalize
        $it = is_object($data) ? (array) $data : $data;
        $this->assertIsArray($it, 'Expected data to be an array or array-like structure');
        $this->assertArrayHasKey('id', $it);
        $this->assertArrayHasKey('code', $it);
        $this->assertArrayHasKey('title', $it);
        $this->assertEquals($coupon->id, $it['id']);
        $this->assertEquals($coupon->code, $it['code']);
        $this->assertEquals($coupon->title, $it['title']);
    }

    public function test_show_returns_not_found_for_missing_coupon()
    {
        $missingId = Str::uuid()->toString();
        Coupon::where('id', $missingId)->delete();

        $controller = new CouponController();
        $request = new Request();
        $response = $controller->show($missingId, $request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean false or a non-success status string
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status to be false for missing coupon');
        } else {
            $this->assertNotEquals('success', $responseData['status'], 'Expected non-success status for missing coupon');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Coupon not found', $responseData['message']);
    }
}